# Cerere ofertă + buton per produs + update automat — design

Data: 2026-11-12
Plugin: Print Options for WooCommerce (`print-options`)

## Intent

Clientul tipografiei poate cere o ofertă pentru produse care nu se pot comanda direct (tiraje mari, produse personalizate, cantități atipice). Butonul trebuie să fie per produs, iar formularul să preia automat numele produsului de pe pagina de pe care s-a intrat, ca să nu fie rescris de client.

Odată ce pluginul e pe GitHub, fiecare modificare publicată trebuie să apară ca actualizare în dashboard, fără reinstalare manuală.

## Goals

- Bifă per produs **„Afișare buton Cere ofertă”** în editorul de produs.
- Buton pe pagina produsului, sub formularul „Adaugă în coș”, vizibil pe orice tip de produs.
- Pagină **Cere ofertă** similară cu referința: card pentru fiecare produs, descriere, cantitate, upload, card de contact, buton de trimitere.
- Produsul se precompletează din pagina produsului (`?pov_product=ID`), dar rămâne editabil.
- Cererile ajung pe email și într-o listă în dashboard, cu status nou/rezolvat și fișiere atașate.
- Push pe GitHub → release automat → „actualizare disponibilă” în WordPress.

## Non-goals

- Nu se generează automat oferte cu preț.
- Nu se creează comenzi WooCommerce din cereri.
- Nu se implementează cont de client / istoric cereri pentru vizitatori.
- Nu se rezolvă spam cu servicii externe (doar nonce, honeypot, rată pe IP).

## Constraints

- Plugin-ul trebuie să rămână compatibil PHP 7.4 și să nu depindă de biblioteci externe.
- Este instalat pe site-uri care pot avea caching și teme diferite (Woodmart/Elementor) — fără suprascrieri de template.
- Volumul de lucru e pe un mount SMB, deci build-ul folosește un director temporar.

## Approaches

### 1. Pagina „Cere ofertă”

| Variant | Trade-off |
|---|---|
| **Pagină reală cu shortcode, creată la activare** (aleasă) | Editabilă din WordPress, fără rewrite rules, funcționează cu orice temă; clientul poate schimba textul din jur. |
| Endpoint virtual prin rewrite | URL fix, dar invizibil în admin și greu de editat de client. |
| Formular direct pe pagina produsului | Nu corespunde referinței (pagină separată) și încarcă pagina produsului. |

### 2. Destinația cererilor

| Variant | Trade-off |
|---|---|
| **Email + CPT în dashboard** (aleasă) | Istoric durabil, status, fișiere; necesită un email configurat. |
| Doar email | Simplu, dar se pierde istoricul. |
| Comandă WooCommerce | Necesită preț, altfel e o comandă invalidă. |

### 3. Update automat

| Variant | Trade-off |
|---|---|
| **GitHub Releases + verificare API din plugin** (aleasă) | Repo public, fără token; zip-ul e construit de CI cu folderul corect; WP afișează actualizarea standard. |
| wp.org | Nu se aplică pentru plugin intern. |
| Verificare fișier brut (raw.githubusercontent) | Fără release-uri, dar livrarea nu are zip corect și nu are istoric. |
| CI care face commit de versionare | Ar produce conflicte la fiecare push (repo-ul local rămâne în urmă). |

## Decisions

- **Versiunea release-ului** = `<major>.<minor din header>.<număr de commituri>`; CI o scrie doar în zip, nu în repo. Astfel fiecare push produce o versiune mai mare, fără commit-uri automate.
- **Butonul** folosește `woocommerce_after_add_to_cart_form`, deci apare sub formular, pe orice tip de produs.
- **Precompletarea** se face server-side din `pov_product`, ca să funcționeze și fără JS; numele rămâne editabil, iar ID-ul sursă se păstrează într-un câmp ascuns.
- **Fișiere**: max 5/produs, max 20 MB, jpg/png/webp/gif/pdf/eps/ai/psd/zip; EPS/AI/PSD sunt acceptate doar după verificarea semnăturii.
- **Spam**: nonce + câmp-capcană + timestamp minim + limită 6 cereri/oră/IP.
- **Actualizarea** se injectează prin `pre_set_site_transient_update_plugins` și `plugins_api`; cache 6h, refresh imediat pe ecranul Plugins când apare un release nou.

## Ce rămâne de făcut (opțional)

- Email de confirmare către client.
- Șabloane de email personalizabile (WooCommerce email classes).
- Export CSV al cererilor.
- Setare globală pentru textul butonului.
