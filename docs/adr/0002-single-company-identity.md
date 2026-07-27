# ADR 0002: Jedna firma a bootstrap hlavního administrátora

## Stav

Přijato 2026-07-19.

## Kontext

StockFlow je nasazován samostatně pro jednu firmu. Veřejná registrace mohla
vytvářet další kořenové účty s oddělenými sklady, zatímco firemní číslování a
reporty předpokládají jediného vlastníka dat.

## Rozhodnutí

- Veřejný endpoint `POST /api/v1/auth/register` neexistuje.
- Deployment má právě jednoho hlavního administrátora. Ten nemá rodiče ani
  přiřazenou pobočku; omezený účet má hlavního administrátora i pobočku.
- `UserSeeder` je idempotentní ve všech prostředích. V prázdné databázi založí
  `test@test.com / password` a sklad, při jednom adminovi nic nemění a při více
  adminech bezpečně selže.
- Osiřelé kořenové účty se před zpřísněním invariantů převedou příkazem
  `php artisan stockflow:migrate-single-company --dry-run` a teprve po kontrole
  ostrým spuštěním.
- Číslování skladových dokladů je firemní podle `(type, year)`.

## Přijaté riziko

Známé bootstrap údaje jsou vědomě přijaté provozní riziko. Aplikace nevynucuje
změnu hesla; provozovatel musí přístup k novému deploymentu omezit a heslo
změnit běžnou správou účtu.
