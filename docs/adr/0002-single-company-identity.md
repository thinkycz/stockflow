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
- Demo seedery smějí běžet pouze v prostředích `local` a `testing`. Deployment
  prostředí `development`, `staging` a `production` seedery nespouštějí.
- V prázdné databázi se hlavní administrátor a sklad vytvoří příkazem
  `php artisan stockflow:admin:bootstrap <email>`. Heslo a potvrzení se zadávají
  skrytě, nejsou součástí argumentů procesu. Stejný příkaz je idempotentní;
  přepínač `--rotate` heslo výslovně změní a zneplatní existující tokeny.
- Příkaz odmítne více administrátorů, osiřelé kořenové účty i jiný existující
  e-mail. `stockflow:identity:diagnose` je povinná produkční kontrola po migraci.
- Osiřelé kořenové účty se před zpřísněním invariantů převedou příkazem
  `php artisan stockflow:migrate-single-company --dry-run` a teprve po kontrole
  ostrým spuštěním.
- Číslování skladových dokladů je firemní podle `(type, year)`.

## Nahrazené riziko

Původně přijaté známé údaje `test@test.com / password` již nejsou v nasazeném
prostředí povolené. Stávající instalace musí heslo změnit příkazem
`php artisan stockflow:admin:bootstrap test@test.com --rotate`; produkční kontrola
do té doby selže.
