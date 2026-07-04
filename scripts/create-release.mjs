#!/usr/bin/env zx

import 'zx/globals'

/**
 * Create the release once the release PR is merged.
 *
 * Config-driven port of the WP Job Manager release tooling. Runs in GitHub
 * Actions: writes the changelog from the (edited) PR body into readme.txt,
 * tags, builds the zip, and creates the GitHub release. WordPress.org SVN
 * deployment is handled by the workflow after this script runs.
 *
 * Usage: `node scripts/create-release.mjs <pr-number>`.
 *
 * External dependencies
 */
import fs from 'node:fs';
import process from 'node:process';
import { execSync } from 'node:child_process';

const REMOTE = 'origin';

/* eslint-disable no-console */

// Load per-repo configuration.
const cfg = JSON.parse( fs.readFileSync( 'release.config.json', 'utf8' ) );

const pluginFileContents = fs.readFileSync( cfg.mainFile, 'utf8' );
const pluginVersion      = pluginFileContents.match( /Version: (.*)/ )[ 1 ].trim();
const pluginName         = pluginFileContents.match( /Plugin Name: (.*)/ )[ 1 ].trim();

const prNumber = process.argv[ 2 ];

const releaseNotes = getReleaseNotes();
updateChangelog();
commitChangelog();
tagRelease();
buildPluginZip();
await createGithubRelease();
setWorkflowStepOutput();
await success();

function getReleaseNotes() {
	const prDescription = JSON.parse( execSync( `gh pr view ${ prNumber } -R ${ cfg.repo } --json body` ).toString() ).body;
	return prDescription
		.match( /### Release Notes\s*\n---([\S\s]*?)---/ )[ 1 ]
		.replace( /^- /gm, '* ' )
		.trim();
}

function updateChangelog() {
	// readme.txt's `== Changelog ==` is the single source of truth. It is the last
	// section in the file; entries are `### <version> - <date>` blocks, newest first,
	// trimmed to the most recent few. Older history lives in git and GitHub releases.
	const newEntry = `### ${ pluginVersion } - ${ new Date().toISOString().slice( 0, 10 ) }\n${ releaseNotes }`;

	let readme = fs.readFileSync( cfg.readme, 'utf8' );

	const section = readme.match( /(== Changelog ==\n+)([\s\S]*)$/ );
	if ( ! section ) {
		throw new Error( `Could not find the == Changelog == section in ${ cfg.readme }.` );
	}

	// Split the existing section body into individual `### version` entries.
	const body     = section[ 2 ].trim();
	const existing = body ? body.split( /\n(?=### )/ ).map( ( entry ) => entry.trim() ) : [];

	// Prepend the new release and keep only the most recent 5 entries.
	const entries = [ newEntry.trim(), ...existing ].slice( 0, 5 );

	readme = readme.replace( /(== Changelog ==\n)[\s\S]*$/, `$1\n${ entries.join( '\n\n' ) }\n` );

	console.log( chalk.bold( 'Adding new release to changelog: ' ) );
	console.log( entries[ 0 ] );

	fs.writeFileSync( cfg.readme, readme );
	console.log( chalk.green( '✓' ), cfg.readme );
}

function commitChangelog() {
	execSync( `git add ${ cfg.readme }` );
	execSync( `git commit -m "Update changelog for ${ pluginVersion }"` );
	execSync( `git push ${ REMOTE } HEAD` );
}

function tagRelease() {
	execSync( `git tag -a ${ pluginVersion } -m "Release ${ pluginVersion }"` );
	execSync( `git push ${ REMOTE } ${ pluginVersion }` );
}

function buildPluginZip() {
	execSync( `make build 1> /dev/null` );
}

function setWorkflowStepOutput() {
	execSync( `echo "version=${ pluginVersion }" >> "$GITHUB_OUTPUT"` );
}

async function createGithubRelease() {
	const pluginZip = `build/${ cfg.slug }.zip`;
	await $`gh release create ${ pluginVersion } -R ${ cfg.repo } --title ${ `Version ${ pluginVersion }` } --notes ${ releaseNotes } ${ pluginZip }`;
}

async function success() {
	console.log( chalk.bold.green( `✓ ${ pluginName } ${ pluginVersion } release created!` ) );
	const comment = `✅ **[${ pluginName } ${ pluginVersion } release](https://github.com/${ cfg.repo }/releases/tag/${ pluginVersion })** created!`;
	await $`gh pr comment ${ prNumber } -R ${ cfg.repo } --edit-last --body ${ comment }`;
}
