<?php

/**
 * Explicit equivalences between an institution name held in ACL and the same
 * institution as the NUC register spells it.
 *
 * Why an explicit table rather than more normalisation rules
 * ----------------------------------------------------------
 * The fuzzy matcher already absorbs ordinary spelling drift. What it cannot do
 * safely is decide that "Baze University, FCT Abuja" and "Leadership
 * University, Abuja, FCT" are different bodies — they are — while
 * "Redeemer's University, Mowe" and "Redeemer's University, Ede" are the
 * same one. Those need a person, and a person's decision belongs in a file
 * that can be read, reviewed and reverted, not inside a scoring function.
 *
 * Keys are names as InstitutionNormalizer produces them, so the table cannot
 * silently fail to match. Values are the register's exact spelling and are
 * verified against the register when this file was generated.
 *
 * An entry is consulted only after exact and fuzzy matching have both declined,
 * so a correct automatic match is never overridden.
 *
 * Not aliased on purpose, having been reviewed and rejected. Each scored
 * 0.6-0.9 against some register entry only because they share a town:
 *
 *   African School of Economics ... African University of Economics  (distinct bodies)
 *   Augustine University ......... Lagos State University, Ojo       (distinct bodies)
 *   Baze University .............. Leadership University, Abuja      (distinct bodies)
 *   Chrisland University ......... Trinity University, Ogun         (distinct bodies)
 *   Crescent University .......... American Open University         (distinct bodies)
 *   Dennis Osadebey University ... Delta State University, Abraka   (distinct bodies)
 *   Edwin Clark University ....... Delta State University, Abraka   (distinct bodies)
 *   Fountain University .......... Osun State University, Osogbo   (distinct bodies)
 *   Greenfield University ........ Kaduna State University         (distinct bodies)
 *   Lighthouse University ........ Wellspring University            (distinct bodies)
 *   Muhammad Kamalud-Deen ........ Kwara State University           (distinct bodies)
 *   Wesley University of Science . Enugu State University of S&T   (distinct bodies)
 *   Sa'adu Zungur University ..... not in the register snapshot at all
 *
 * The last five groups are genuine absences: real universities the captured
 * register of 2026-09-05 does not list, or lists under a name this could not
 * confirm. They keep their existing classification rather than being forced
 * onto a similar-looking neighbour.
 */

return [
    'abdulrasaq abubakar toyin university oke agba kwara' => 'Abdulrasaq Abubakar Toyin University, Oke-Ogba, Ganmo, Ilorin, Kwara State',
    'adeyemi federal university education akure ondo state' => 'Adeyemi Federal University of Education, Ondo',
    'amaj university abuja fct' => 'Amaj University, Kwali, Abuja',
    'arthur jarvis university akpabuyo cross river state' => 'Arthur Javis University Akpoyubo Cross river State',
    'bamidele olumulia university education science and technology' => 'Bamidele Olumilua University of Science and Technology Ikere, Ekiti State',
    'caleb university imota lagos state' => 'Caleb University, Lagos',
    'cross river university technology calabar' => 'University of Cross River State, Calabar',
    'elrazi university medical sciences kano kano state' => 'Elrazi Medical University Yargaya University, Kano State',
    'emmanuel alayande university education oyo oyo state' => 'Emanuel Alayande University of Education Oyo',
    'enugu state university science and tech enugu' => 'Enugu State University of Science and Technology, Enugu',
    'federal university agriculture bassam bari bayelsa' => 'Federal University of Agriculture Bassam-Biri, Bayelsa',
    'federal university education kantagora niger state' => 'Federal University of Education Kontagora, Niger State',
    'glorious vision university formerly samuel adegboyega university' => 'Glorious Vision University, Ogwa, Edo State.',
    'hallmark university ijebu itele ogun state' => 'Hallmark University, Ijebi Itele, Ogun',
    'huda university gusau zamfara state' => 'Huda University, Gusau, Zamafara State',
    'jigawa state universitiy allied and medical sciences jigawa' => 'Jigawa State University of Medical and Allied Health Sciences, Majia',
    'khalifa isiyaku rabiu university gadon kaya city gatto kano state' => 'Khalifa Isiyaku Rabiu University, Kano',
    'king david umahi university medical sciences uburu ebonyi state' => 'David Nweze Umahi Federal University of Medical Sciences, Uburu',
    'lagos university education lasued oto ijanikin lagos state' => 'Lagos State University of Education, Ijanikin',
    'margaret lawrence university galilee delta state' => 'Margaret Lawrence University, Umunede, Delta State',
    'maranatha university lekki lagos state' => 'Maranatha University, Lagos',
    'mercy medical university iwara iwo osun state' => 'Mercy Medical University, Iwo, Ogun State',
    'michael and cecilia ibru university owhode delta state' => 'Micheal & Cecilia Ibru University',
    'millennium crest university ikare akoko' => 'Millenium Crest University, Ikare Akoko, Ondo State',
    'olusegun agagu university science and technology okitipupa' => 'Olusegun Agagu University of Sc. & Tech., Okitipupa, Ondo',
    'pamo university medical sciences port harcourt rivers state' => 'PAMO University of Medical Sciences, Portharcourt',
    'pan atlantic university lekki express way lagos state' => 'Pan-Atlantic University, Lagos',
    'precious cornerstone ibadan oyo state' => 'Precious Cornerstone University, Oyo',
    'rayhaan university birnin kebbi kebbi state' => 'Rayhaan University, Kebbi',
    'redeemer s university mowe ogun state' => 'Redeemer\'s University, Ede',
    'rev fr moses orshio adasu university makurdi' => 'Rev. Fr. Moses Orshio Adasu (Formerly, Benue State University), Makurdi',
    'sam maris university supare ondo state' => 'Sam Maris University, Ondo',
    'skyline university nigeria kano kano state' => 'Skyline University, Kano',
    'transatlantic university medicine and health sciences umuchukwu' => 'Transatlantic University of Medine and Health Sciences, Umuchukwu, Anambra State',
    'umaru musa yar adua university katsina' => 'Umar Musa Yar\'Adua University Katsina',
    'university benin benin city' => 'University of Benin',
    'university mkar mkar benue state' => 'University of Mkar, Mkar',
    'usmanu danfodiyo university sokoto' => 'Usumanu Danfodiyo University',
    'borno state university' => 'Kashim Ibrahim (formerly Bornu State) University, Maiduguri',
    'covenant university otta ogun state' => 'Covenant University Ota',
];
