# Arkkitehtuuri

## Kerrokset

```
Infrastructure/WordPress   REST-rajapinta, $wpdb-repositoriot, roolit, taulut
        │  (riippuu)
Application                Käyttötapaukset (TenderService, BidService), validointi, JSON-muoto
        │
Domain                     Tender, Bid, AccessPolicy, säännöt, rajapinnat repositorioille
```

`Domain` ja `Application` eivät kutsu yhtään WordPress-funktiota. Kun
palvelu siirretään omaksi sovelluksekseen (esim. Laravel tai Symfony), uusiksi
kirjoitetaan vain `Infrastructure`-kerros: repositoriot toiseen tietokantaan,
REST-reitit ja kirjautuminen. Tietokantataulut voi siirtää sellaisenaan.

## Suljettujen tarjousten sääntö

Tarjoukset avautuvat, kun **nykyhetki ≥ määräpäivä** (`Tender::areBidsRevealed`).
Tieto päätellään joka pyynnöllä kellosta, eikä se riipu ajastetusta tehtävästä.
Jos WP-Cron ei käy, tarjoukset eivät avaudu liian aikaisin eivätkä jää jumiin.

- Ennen määräpäivää: kukaan muu kuin tarjouksen jättäjä ei näe tarjousta, ei edes ylläpitäjä.
- Ennen määräpäivää peruttu kilpailutus ei avaa tarjouksia koskaan.
- Määräpäivää voi vain siirtää myöhemmäksi ja vain tarjousajan ollessa käynnissä.
- Jokainen tarjousten avaus kirjataan `tk_audit_log`-tauluun. Lokissa ei ole tarjousten sisältöä.
- Tilaaja ja ylläpitäjä näkevät tarjousten **määrän**, eivät sisältöä.
- Kilpailutus, jota käyttäjä ei saa nähdä, palauttaa 404, ei 403. Näin tunnisteita ei voi arvailla.

Huom: tietokannan ylläpitäjä (palvelin, phpMyAdmin) voi teknisesti lukea
taulut. Jos tämäkin halutaan estää, tarjoukset voidaan myöhemmin salata
määräpäivään asti (esim. libsodium ja avain, joka vapautuu vasta määräpäivänä).

## Tietokantataulut

| Taulu | Sisältö |
|---|---|
| `wp_tk_tenders` | kilpailutukset |
| `wp_tk_bids` | tarjoukset, `UNIQUE (tender_id, contractor_id)` |
| `wp_tk_audit_log` | tapahtumaloki |

Ajat ovat UTC:nä ja rahasummat sentteinä (`bigint`). Skeeman versio on optiossa
`tk_db_version`, ja päivitys ajetaan automaattisesti `dbDelta`:lla.

## REST-rajapinta

Kaikki reitit vaativat kirjautumisen (WordPressin evästeautentikointi ja
`X-WP-Nonce`-otsake). Virheet palautetaan muodossa
`{code, message, data: {status, fields?}}`.

| Metodi | Polku | Kuka | Mitä |
|---|---|---|---|
| GET | `/kilpailutus/v1/renovation-types` | kaikki | remonttityypit |
| GET | `/kilpailutus/v1/tenders` | kaikki | roolin mukainen lista |
| POST | `/kilpailutus/v1/tenders` | taloyhtiö | luo luonnoksen |
| GET | `/kilpailutus/v1/tenders/{id}` | näkyvyyden mukaan | yksi kilpailutus |
| PATCH | `/kilpailutus/v1/tenders/{id}` | omistaja | muokkaa luonnosta |
| POST | `/kilpailutus/v1/tenders/{id}/publish` | omistaja | julkaise |
| POST | `/kilpailutus/v1/tenders/{id}/deadline` | omistaja | siirrä määräpäivää `{deadline}` |
| POST | `/kilpailutus/v1/tenders/{id}/cancel` | omistaja | peru |
| POST | `/kilpailutus/v1/tenders/{id}/award` | omistaja | valitse `{bid_id}` |
| GET | `/kilpailutus/v1/tenders/{id}/bids` | omistaja/ylläpitäjä määräpäivän jälkeen | kaikki tarjoukset hinnan mukaan |
| GET | `/kilpailutus/v1/tenders/{id}/bids/mine` | urakoitsija | oma tarjous |
| PUT | `/kilpailutus/v1/tenders/{id}/bids/mine` | urakoitsija | jätä tai päivitä tarjous |
| DELETE | `/kilpailutus/v1/tenders/{id}/bids/mine` | urakoitsija | peru tarjous |

Esimerkki, tarjouksen jättäminen:

```json
PUT /wp-json/kilpailutus/v1/tenders/12/bids/mine
{
  "price_excl_vat": "185000,00",
  "start_date": "2027-05-03",
  "completion_date": "2027-08-15",
  "warranty_months": 24,
  "description": "Sisältää purun ja kierrätyksen.",
  "references": "As Oy Malli 2025"
}
```
