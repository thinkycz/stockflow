# ADR 0006: Tříreceptové testovací sezení

- Stav: accepted
- Datum: 2026-08-02

## Rozhodnutí

Nové testy používají parent `RecipeTestSession` a přesně tři existující
`RecipeTestAttempt` child záznamy. Child pokus zůstává jednotkou adminské historie
jednoho receptu; parent zajišťuje atomické odevzdání, společný výsledek a audit.

Správné číselné množství se uchovává jen v serverovém snapshotu. Testovací payload
obsahuje pro `g`/`ml` pouze surovinu, jednotku, cíl a neprůhledný token.

## Důsledky

- Legacy single-recipe pokusy lze dokončit původní GET/PUT cestou.
- Nový POST single-recipe pokusu se ruší ve prospěch session endpointu.
- Historie může dál agregovat per recept a současně odkazovat na celé sezení.
