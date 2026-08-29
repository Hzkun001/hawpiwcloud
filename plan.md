# hawpiwcloud Security Plan

1. Add a shared bootstrap for secure sessions, CSRF, authentication guards, environment validation, and the private file path.
2. Add one login endpoint that handles login and POST-only logout, including a small file-backed login throttle.
3. Require authentication in every existing endpoint and replace `uploads/` access with the validated private `files/` directory.
4. Keep original sanitized names when available; on collision, add a random suffix until an unused target is found.
5. Reuse the current dashboard styles for the login screen and replace the misleading navigation login link with logout.
6. Document a downtime migration: back up files, move them outside the document root, verify them, then remove the old public directory.
7. Validate with PHP lint and one temporary-directory HTTP smoke test.
8. Improve the existing dashboard with native disclosure controls, early upload-size feedback, submit progress, keyboard focus, accurate copy, corrected anchors, and reduced-motion support while preserving the footer and visual design.

Rollback requires restoring the previous code and moving the backed-up files to their former location. The private directory is never used as an automatic fallback to public storage.
