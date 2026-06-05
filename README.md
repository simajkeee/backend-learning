# Symfony slice-practice

Personal learning repo for practicing Symfony 7.4 patterns: entities,
migrations, controllers, services, Messenger, reporting. Not a production app.

## Setup

```bash
cp .env.example .env.local
# edit .env.local with real values (APP_SECRET, DATABASE_URL, ...)
composer install
bin/console doctrine:migrations:migrate
```

## Notes

- `.env`, `.env.dev`, `.env.test` are gitignored — use `.env.local` and
  `.env.<env>.local` for local overrides.