# Inertia validace vykreslí Login

## Symptom

Pokus o uzavření finančního reportu před uzavřením výplatního reportu nahradí aktuální stránku přihlašovací obrazovkou, přestože uživatel zůstává přihlášený.

## Evidence

- Zachycený Inertia payload má komponentu `auth/Login` a současně obsahuje přihlášeného `auth.user` s `is_admin: true`.
- Payload obsahuje očekávanou validační chybu `errors.report`.
- `FinancialReportService::close()` správně vyhodí `ValidationException`; session ani autentizační middleware uživatele neodhlašují.

## Root cause

Globální Inertia renderer `ValidationException` v `bootstrap/app.php` vrací přímou `422` Inertia stránku. Běžný mutační požadavek neposílá `X-Inertia-Partial-Component`, takže renderer použije výchozí komponentu `auth/Login`. Jde o sdílenou chybu všech webových Inertia validací, nikoliv o lokální chybu finančního reportu.

## Oprava

Globální handler nyní pro Inertia požadavky vrací redirect na explicitní `redirectTo`, případně na předchozí URL. Do session přenáší původní error bag a bezpečný vstup bez hesel a jejich potvrzení. Prázdný error bag z aplikační `ValidationException` normalizuje na standardní `default` bag.

Přímé uživatelské mutace přes Inertia router používají sdílený wrapper, který zobrazí první validační zprávu jako trvalý červený toast a zachová původní `onError`. Formuláře přes `useForm` zůstávají bez tohoto wrapperu, aby chyby nebyly duplicitní vedle polí. API/JSON validace nadále vrací `422`.

## Guardrail

- Nevykreslovat stránkovou Inertia komponentu přímo z globálního handleru validačních výjimek.
- Nové samostatné `router.post`, `router.put`, `router.patch` a `router.delete` akce obalit helperem pro chybový toast; neaplikovat jej na `useForm` ani logout.
- Regresní test musí po chybě ověřit redirect, zachovaného uživatele a komponentu sestavenou následným GET požadavkem.
