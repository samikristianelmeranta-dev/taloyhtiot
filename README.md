# Taloyhtiöiden remonttikilpailutus

Palvelu, jossa taloyhtiö tai isännöitsijä kilpailuttaa remontin ja urakoitsijat
jättävät **suljetut tarjoukset**. Tarjoukset avautuvat tilaajalle vasta
määräpäivänä. Palvelun ylläpitäjäkään ei näe niitä etukäteen.

Ensimmäinen versio tehdään WordPress-lisäosana omaan WordPress-asennukseen,
erilleen ikkunakauppias.fi:stä. Rakenne on valittu niin, että palvelun voi
myöhemmin siirtää omaksi sovelluksekseen (ks. [docs/ARKKITEHTUURI.md](docs/ARKKITEHTUURI.md)).

## Sisältö

| Polku | Mitä |
|---|---|
| `plugin/taloyhtio-kilpailutus/` | WordPress-lisäosa (asennettava kansio) |
| `plugin/.../src/Domain/` | Liiketoimintasäännöt ilman WordPressiä: kilpailutus, tarjous, käyttöoikeudet |
| `plugin/.../src/Application/` | Käyttötapaukset (luo, julkaise, jätä tarjous, avaa tarjoukset) ja syötteen validointi |
| `plugin/.../src/Infrastructure/WordPress/` | Tietokantataulut, roolit, REST-rajapinta |
| `tests/` | Yksikkötestit (ajetaan ilman WordPressiä) |
| `docs/VAATIMUKSET.md` | Vaatimusmäärittely |
| `docs/ARKKITEHTUURI.md` | Rakenne, säännöt ja REST-rajapinta |

## Kehitys

```bash
composer install
composer test        # PHPUnit
composer lint        # php -l kaikille lisäosan tiedostoille
bin/build-zip.sh     # build/taloyhtio-kilpailutus.zip WordPressiin asennettavaksi

# Savutesti oikeaa WordPressiä vasten (vain testiasennuksessa, lisäosa aktivoituna):
php bin/wp-smoke-test.php /polku/wordpressiin
```

Savutesti ajaa koko ketjun REST-rajapinnan ja tietokannan kautta:
luonti, julkaisu, tarjoukset, sulkeutuminen, avaus ja valinta.

Vaatimukset: PHP 8.1+, WordPress 6.4+, MySQL/MariaDB.

## Tila

Valmiina (versio 0.1.0, ydin):

- roolit: taloyhtiö/isännöitsijä, urakoitsija, ylläpitäjä
- kilpailutukset ja tarjoukset omissa tietokantatauluissa
- suljetut tarjoukset, jotka avautuvat määräpäivänä; ei riipu WP-Cronista
- käyttöoikeustarkistukset yhdessä paikassa (`AccessPolicy`) ja testattuina
- tapahtumaloki (kuka avasi tarjoukset ja milloin)
- REST-rajapinta `/wp-json/kilpailutus/v1/`

Seuraavaksi: ks. kohta "Toteutusjärjestys" tiedostossa [docs/VAATIMUKSET.md](docs/VAATIMUKSET.md).
