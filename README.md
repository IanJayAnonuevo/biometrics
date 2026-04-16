# Biometrics Attendance System

Simple PHP-based biometrics attendance web app running on XAMPP (Apache + MySQL).

## Requirements

- Windows 10/11
- [XAMPP](https://www.apachefriends.org/)
- PHP and MySQL (included in XAMPP)
- Web browser (Chrome, Edge, Firefox)

## Project Location

Place this project inside your XAMPP `htdocs` folder:

`C:\xampp\htdocs\biometrics`

## Secure Local Setup

1. Open **XAMPP Control Panel**.
2. Start **Apache**.
3. Start **MySQL**.
4. Create/import the database:
   - Open `http://localhost/phpmyadmin`
   - Create database `biometrics` (if not existing)
   - Import `biometrics.sql`
5. Create your local secret file:
   - Copy `.env.example` to `.env`
   - Fill in your real values (DB, admin login, Microsoft OAuth secret)
   - `ADMIN_EMAIL` and `ADMIN_PASSWORD` are required for initial local admin login

PowerShell copy command:

`Copy-Item .env.example .env`

CMD copy command:

`copy .env.example .env`

## Run the App (Development)

This repository currently runs as a PHP app through Apache (no `npm run dev` setup in this folder).

Open in browser:

- `http://localhost/biometrics/`

## Environment Variables

`config.php` now reads values from `.env`.  
If `.env` is missing, safe defaults/placeholders are used.

Common keys:

- `DB_HOST`
- `DB_USER`
- `DB_PASS`
- `DB_NAME`
- `PORT`
- `ADMIN_EMAIL`
- `ADMIN_PASSWORD`
- `MS_CLIENT_ID`
- `MS_CLIENT_SECRET`
- `MS_REDIRECT_URI`

## Security Notes

- Never commit `.env` (already ignored in `.gitignore`).
- Keep `MS_CLIENT_SECRET` only in `.env`, never in tracked PHP files.
- If a secret was previously exposed, rotate/revoke it immediately.

## Optional Files

- `config.example.php` is a reference template for non-`.env` setups.

