# Triage Labels

The skills speak in terms of five canonical triage roles. This file maps those roles to the actual label strings used in this repo's issue tracker.

| Canonical role     | Label in our tracker | Meaning                                  |
| ------------------ | -------------------- | ---------------------------------------- |
| `needs-triage`     | `needs-triage`       | Maintainer needs to evaluate this issue  |
| `needs-info`       | `needs-info`         | Waiting on reporter for more information |
| `ready-for-agent`  | `ready-for-agent`    | Fully specified, ready for an AFK agent  |
| `ready-for-human`  | `ready-for-human`    | Requires human implementation            |
| `wontfix`          | `wontfix`            | Will not be actioned                     |

Category labels (applied alongside one state label): `bug`, `enhancement`.

When a skill mentions a role (e.g. "apply the AFK-ready triage label"), use the corresponding label string from this table.

## Repo-specific notes

- `wontfix`, `bug`, `enhancement` already exist in the repo.
- `needs-triage`, `needs-info`, `ready-for-agent`, `ready-for-human` were created during setup.
- The repo has other historical labels (`Needs Review`, `reporter feedback`, `PRIORITY`, `[Status] In Progress`, etc.) that are **not** part of the triage state machine. Leave them alone unless asked.
