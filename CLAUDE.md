# Claude Rules — NVH Admin Backend

## Branch Policy

- All new features must be developed on the `dev` branch.
- You may only commit or push to the `dev` branch.
- Never commit or push directly to `staging`, `main`, `master`, or `13.x`.
- Before making any commit, verify the current branch with `git branch --show-current`.
- If you are not on `dev`, stop and ask the user before proceeding.

## Workflow

- The branching flow is: `dev` → `staging` → `prod`.
- `dev` is where all active development happens.
- `staging` receives merges from `dev` for testing.
- `prod` (or `main`/`13.x`) receives merges from `staging` only after approval.
- Before starting any work on `dev`, always pull the latest changes: `git checkout dev && git pull origin dev`.

## Push Policy

- Never push to GitHub without explicit approval from the user.
- After committing, always present the changes and wait for the user to test and confirm before running `git push`.
- Only push when the user says so (e.g. "push it", "go ahead", "push to feature branch").

## Migration Policy

- Never edit an existing migration file once it has been run on any environment (staging or prod). It is frozen.
- All schema changes — even small ones — must be written as a new migration file.
- Before running `php artisan migrate` on prod, always run `php artisan migrate --pretend` first to preview the SQL that will execute.
- If a migration was skipped on prod due to this rule being violated, write a new compensating migration to apply the missing change rather than fixing it manually in Tinker.

## Documentation Policy

- After implementing any new feature or step, always update `docs/API.md` to document what was built and how the API works.
- Documentation must be committed in the same step as the feature code — never skip it.
- `docs/API.md` must include: endpoint method + path, description, required headers, request body (if any), and example response.

