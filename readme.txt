=== Pop-up ===
Contributors: adventistai
Tags: popup, banner, modal, image, campaign
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.0.1
License: GPLv2 or later

Iššokantis langas su paveikslėliu (pvz., kampanijos reklama), nustatomas Nustatymai → Pop-up.

== Ką daro ==

* Rodo paveikslėlį iššokančiame lange virš puslapio; paveikslėlis gali būti nuoroda (pasirinktinai naujame skirtuke).
* Paveikslėlis renkamas iš mediatekos arba nurodomas adresu.
* Kur rodyti: tik pradiniame puslapyje, visuose puslapiuose arba pasirinktuose puslapiuose.
* Kaip dažnai: kartą per apsilankymą, kartą per nurodytą dienų skaičių arba kiekvieną kartą.
* Delsa po puslapio įkėlimo, rodymo laikotarpis (nuo–iki), įrenginiai (visi, tik kompiuteriai, tik telefonai).
* Fono tamsumas, didžiausias plotis, uždarymas paspaudus šalia paveikslėlio.
* Pakeitus paveikslėlį ar nuorodą, langas iš naujo parodomas ir tiems, kurie jau matė senąjį.
* Peržiūra: administratorius mato langą adresu svetainė/?wppu_preview=1 nepaisant kitų nustatymų.
* Prieinamumas: dialogo vaidmuo, fokusas lange, uždarymas klavišu Esc, fokusas grąžinamas uždarius, sumažinto judesio nustatymo paisoma.
* Datos, įrenginys ir „jau matyta“ tikrinami naršyklėje, todėl veikia ir su puslapių podėliu.
* Neįrašo slapukų: „jau matyta“ saugoma naršyklės sesijos arba vietinėje saugykloje.

== Diegimas ==

1. Įkelkite wp-pop-up.zip: Įskiepiai → Pridėti naują → Įkelti įskiepį.
2. Įjunkite įskiepį.
3. Nustatymai → Pop-up: pasirinkite paveikslėlį, nuorodą ir kur bei kada rodyti.

Atnaujinimai ateina iš GitHub leidimų (https://github.com/kiritoshiro/wp-pop-up/releases) per įprastą WordPress atnaujinimų ekraną.

== Changelog ==

= 1.0.1 =
* Saugumas: atnaujinimas įdiegiamas tik tada, kai atsisiųsto paketo SHA-256 sutampa su GitHub leidimo API pateikiamu paketo kontroliniu kodu (digest). Leidimas be kontrolinio kodo nesiūlomas, o nesutapus kodui atnaujinimas sustabdomas. Atsisiuntimas leidžiamas tik iš GitHub leidimų failų serverių.

= 1.0.0 =
* Pirmasis leidimas: iššokantis langas su paveikslėliu ir nustatymų puslapiu (paveikslėlis, nuoroda, puslapiai, dažnumas, delsa, laikotarpis, įrenginiai, išvaizda), peržiūra administratoriams, atnaujinimai iš GitHub leidimų.
