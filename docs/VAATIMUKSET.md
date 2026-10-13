# Vaatimusmäärittely

## Tavoite

Taloyhtiöiden remonttien kilpailutuspalvelu. Taloyhtiö tai isännöitsijä luo
kilpailutuksen, urakoitsijat jättävät suljetut tarjoukset, ja tilaaja vertailee
tarjouksia määräpäivän jälkeen sekä valitsee urakoitsijan.

## Periaatteet

1. **Puolueettomuus.** Palvelulla on oma nimi ja domain, ja se on erillään
   Nordic Ikkunat & Ovet Oy:stä ja ikkunakauppias.fi:stä. Ylläpitäjä ei näe
   tarjouksia ennen määräpäivää. Tämä on toteutettu koodissa, ei pelkkänä lupauksena.
2. **Luottamuksellisuus.** Urakoitsija näkee vain oman tarjouksensa. Tilaaja
   näkee tarjoukset vasta määräpäivän jälkeen.
3. **Oma hallinta.** Ei valmiita marketplace-, lomake- tai WooCommerce-lisäosia.
   Oikeudet ja tietoturva ovat omassa koodissa.
4. **Siirrettävyys.** Data on omissa tauluissa, ja liiketoimintalogiikka on
   erillään WordPressistä.
5. **Erillinen asennus.** Oma GitHub-repositorio ja oma WordPress-asennus.

## Käyttäjäroolit

| Rooli | WordPress-rooli | Oikeudet |
|---|---|---|
| Taloyhtiö / isännöitsijä | `tk_taloyhtio` | Luo, muokkaa (luonnos), julkaisee ja peruu omia kilpailutuksia. Näkee omien kilpailutustensa tarjoukset määräpäivän jälkeen. Valitsee urakoitsijan. |
| Urakoitsija | `tk_urakoitsija` | Näkee julkaistut kilpailutukset. Jättää, muokkaa ja peruu oman tarjouksensa määräpäivään asti. |
| Ylläpitäjä | `administrator` + `tk_manage` | Näkee kaikki kilpailutukset ja tarjousmäärät. Tarjousten sisällön vain määräpäivän jälkeen, ja jokainen avaus kirjataan lokiin. |

## Toiminnalliset vaatimukset

### Kilpailutus (toteutettu: ydin)
- Kentät: otsikko, remonttityyppi, kuvaus, taloyhtiön nimi, osoite,
  toivottu aloitus ja valmistuminen, tarjousten määräpäivä.
- Tilat: luonnos → julkaistu (tarjousaika → tarjoukset avattu) → urakoitsija valittu, tai peruttu.
- Julkaistua kilpailutusta ei voi muokata, jotta kaikki tarjoajat saavat saman
  tiedon. Määräpäivää voi vain siirtää myöhemmäksi tarjousajan aikana.
- Tarjousajan vähimmäispituus on 72 h (säädettävissä suodattimella `tk_minimum_bid_period_hours`).
- Ilman aikavyöhykettä annetut ajat tulkitaan Suomen ajaksi. Tallennus tehdään UTC:nä.

### Tarjous (toteutettu: ydin)
- Kentät: hinta (alv 0 %, sentteinä), aloitus, valmistuminen, takuu (kk),
  kuvaus, referenssit.
- Yksi tarjous per urakoitsija per kilpailutus. Muokattavissa ja peruttavissa määräpäivään asti.
- Tilaaja voi valita vain jätetyn tarjouksen ja vasta määräpäivän jälkeen.

### Jatkossa (toteutusjärjestys)
1. **Käyttöliittymä**: taloyhtiön kilpailutusnäkymä, urakoitsijan
   tarjouslomake ja vertailutaulukko (lisäosan lyhytkoodit tai lohkot, jotka käyttävät REST-rajapintaa).
2. **Kilpailutuksen luonti vaiheittain** (ohjattu lomake).
3. **Yksityiset liitteet**: kuntoarvio, piirustukset ja tarjousliitteet.
   Tiedostot tallennetaan julkisen hakemiston ulkopuolelle ja jaetaan vain
   oikeustarkistetun latauksen kautta, joten ne eivät aukea suoralla linkillä.
4. **Kysymykset ja vastaukset**: urakoitsijan kysymykset ja vastaukset
   näkyvät kaikille tarjoajille ilman kysyjän nimeä.
5. **Sähköposti-ilmoitukset**: uusi kilpailutus alueella, uusi tarjous
   (vain määrä), määräpäivä lähestyy, tarjoukset avattu ja valinta tehty.
6. **Vertailutaulukko ja PDF-yhteenveto** hallituksen kokoukseen.
7. **Urakoitsijaprofiili**: toimialat, alueet, Y-tunnuksen tarkistus (YTJ)
   ja tilaajavastuuasiakirjat. Luotettava Kumppani -integraatio myöhemmin.
8. **Rekisteröityminen ja hyväksyntä**: urakoitsijan tunnus aktivoituu ylläpitäjän tarkistuksen jälkeen.
9. **Ansaintamalli** (myöhemmin): urakoitsijan kuukausimaksu, maksu per
   tarjous tai maksu taloyhtiöltä per kilpailutus.
10. **Tietosuojaseloste ja käyttöehdot**: rekisterinpitäjä ja kuka näkee mitäkin.

## Avoimet kysymykset

Seuraavat asiat odottavat päätöstä. Suluissa on oletus, jonka mukaan
ydin on nyt tehty.

1. **Laajuus**: vain ikkuna- ja oviremontit vai kaikki taloyhtiöremontit?
   (Oletus: kaikki tyypit ovat listalla, ja ikkunat ja ovet ovat ensimmäisinä. Listaa voi rajata.)
2. **Pääkäyttäjä**: isännöitsijä vai hallituksen puheenjohtaja? (Oletus:
   sama rooli molemmille. Isännöitsijällä voi olla useita taloyhtiöitä.)
3. **Maksaja**: urakoitsijat, taloyhtiöt vai aluksi ilmainen? (Oletus: ilmainen, ei maksulogiikkaa.)
4. **Nimi ja domain**: ei vielä päätetty. Lisäosa käyttää neutraalia nimeä "Taloyhtiön remonttikilpailutus".
