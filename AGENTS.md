# Project instructions

- Use a task branch for normal feature, fix, and refactor work; do not change `main` directly.
- Preserve public URLs and existing behavior unless the task explicitly requires a change. Keep unrelated edits out of the branch.
- Keep the PHP, CSS, and JavaScript architecture lightweight. Add frameworks or dependencies only for a concrete need.
- Follow the game-data precedence in `docs/game-data.md`. Never silently change Diablo, Hellfire, or DevilutionX mechanics; document evidence and owner-approved corrections.
- Use semantic, accessible markup and secure defaults. Treat every committed file as public and never commit secrets or private data.
- Run the relevant checks in `docs/development.md` before considering work complete, including calculator regression checks when mechanics or UI logic changes.
