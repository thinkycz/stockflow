<?php

declare(strict_types=1);

return [
    'password_init' => [
        'subject' => 'Žiadosť o nastavenie hesla',
        'line1' => 'Túto správu ste dostali na základe žiadosti o nastavenie hesla.',
        'action' => 'Nastaviť heslo',
        'line2' => 'Platnosť tohto odkazu na nastavenie hesla vyprší o :count minút.',
        'line3' => 'Ak ste o nastavenie hesla nežiadali, správu odstráňte. Pôvodné heslo zostalo nezmenené.',
    ],
    'password_reset' => [
        'subject' => 'Žiadosť o obnovenie hesla',
        'line1' => 'Tento e-mail ste dostali, pretože sme prijali žiadosť o obnovenie hesla k vášmu účtu.',
        'action' => 'Obnoviť heslo',
        'line2' => 'Platnosť tohto odkazu na obnovenie hesla vyprší o :count minút.',
        'line3' => 'Ak ste o obnovenie hesla nežiadali, nemusíte robiť nič.',
    ],
];
