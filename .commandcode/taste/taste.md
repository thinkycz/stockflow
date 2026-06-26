# Taste (Continuously Learned by [CommandCode][cmd])

[cmd]: https://commandcode.ai/

# php

- Import all qualifiers instead of using fully qualified names. Confidence: 0.75
- Use explicit getter methods to access model properties instead of magic @property annotations. Confidence: 0.75
- Call Eloquent scopes via `$query->tap(fn() => Model::scopeX($query))` instead of relying on magic method resolution. Confidence: 0.70
- Use `Env::inject()` instead of `env()` or `\env()` calls. Confidence: 0.75

# phpstan

- Keep PHPStan at level `max` and aim to remove the baseline completely instead of adding broad suppressions. Confidence: 0.70
