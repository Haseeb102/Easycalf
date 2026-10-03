# EasyCalf Installation Guide

## Requirements
- PHP 8.0 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx)

Point the document root at the `public` directory so the rest of the project, including configuration, is outside the web root.

## Configuration

Database credentials are not stored in this repository. Set them in the environment, or in a file that is gitignored and kept outside the web root. The application stops with a clear error if `DB_HOST`, `DB_NAME`, `DB_USER`, or `DB_PASS` is missing. There are no built-in fallback values.

Copy `.env.example` and fill in your own values. The file is read from the first of these that exists:

1. Environment variables already set by the server (these win over a file).
2. The absolute path in `EASYCALF_ENV_FILE`.
3. A file next to the project directory, named after that directory with a `.env` suffix. For a project folder named `easycalf`, that path is the sibling `easycalf.env`.
4. A `.env` file in the project root (the directory that contains `public/`, not inside `public/`).

Keep `APP_DEBUG=false` on any server other people can reach. Debug and migration routes stay unavailable unless `APP_DEBUG` is `true`.

## Install with the browser

1. Upload the files and point the document root at `public/`.
2. Create an empty MySQL database and a database user. Keep the password in your host panel or in the configuration file only.
3. Open the `install/` page and enter the database settings plus a new administrator name, email, and password. The installer writes those settings to the configuration file and creates the administrator account. Nothing is pre-filled.
4. Delete or block the `install` directory after it finishes.
5. Sign in with the administrator account you just created.

There is no default administrator password.

## Install by hand

1. Create the database and set the configuration values described above.
2. Import `database/schema.sql`.
3. Create the first administrator with a password hash you generate locally. Do not commit the hash. Seed data expects this user to be id 1.

Set `ADMIN_PASSWORD` in your environment to the password you chose, then run:

```
php -r 'if (getenv("ADMIN_PASSWORD") === false || getenv("ADMIN_PASSWORD") === "") { fwrite(STDERR, "Set ADMIN_PASSWORD in the environment first.\n"); exit(1); } echo password_hash(getenv("ADMIN_PASSWORD"), PASSWORD_DEFAULT), PHP_EOL;'
```

```sql
INSERT INTO `users` (`name`, `email`, `password`, `role`, `status`, `approved_at`)
VALUES ('Administrator', 'you@example.com', 'PASTE_PASSWORD_HASH_HERE', 'admin', 'active', NOW());
```

4. Import `database/seed.sql`.
5. Delete or block the `install` directory.

## If this repository was ever public

Removing credentials from the current files does not remove them from older revisions or from anyone who already copied them. Change the database password and every administrator password on the server, and consider making the repository private.
