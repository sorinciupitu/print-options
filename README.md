# Print Options for WooCommerce

Opțiuni vizuale, cantități și prețuri pentru produsele WooCommerce, plus fluxul **Cere ofertă**: un buton pe orice produs care deschide o pagină de cerere de ofertă cu produsul pre-completat.

## Ce conține

- Buton per produs **„Afisare buton Cere ofertă”** (bifă în editorul de produs, tab *Cerere ofertă*).
- Pagina **Cere ofertă** creată automat la activare, cu shortcode-ul `[pov_quote_form]`.
- Formular cu mai multe produse, descriere, cantitate, încărcare fișiere și date de contact.
- Cererile sunt salvate în **WooCommerce → Cereri ofertă** și trimise pe email.
- Actualizare automată din GitHub Releases, direct din dashboard-ul WordPress.

## Instalare

1. Descarcă `print-options.zip` din [ultima versiune](https://github.com/sorinciupitu/print-options/releases/latest).
2. WordPress → Plugins → Adaugă plugin → Încarcă plugin → instalează și activează.
3. La activare se creează pagina **Cere ofertă** (dacă nu există deja).

## Configurare

### Butonul „Cere ofertă” pe un produs

Produs → **Opțiuni vizuale produs** → tab **Cerere ofertă** → bifează **Afișare buton „Cere ofertă”** → Publică.

Butonul apare sub butonul „Adaugă în coș” și deschide pagina *Cere ofertă* cu produsul completat automat (`?pov_product=ID`).

### Pagina și cererile

- Pagina: **Pagini → Cere ofertă** (conține shortcode-ul `[pov_quote_form]`, poți edita titlul și textul din jur).
- Destinatar email: **WooCommerce → Cereri ofertă**, câmpul *Email destinatar cereri* (implicit adresa de admin).
- Lista cererilor: **WooCommerce → Cereri ofertă**, cu status *Nouă / Rezolvată*, fișiere atașate și link către pagina sursă.

## Actualizări automate

Fiecare push pe `main` care modifică fișierele pluginului (`print-options.php`, `includes/`, `assets/`, `readme.txt`) declanșează workflow-ul [`.github/workflows/release.yml`](.github/workflows/release.yml):

1. rulează `php -l` pe toate fișierele;
2. calculează versiunea: `1.<minor din header>.<număr de commituri>`;
3. construiește `print-options.zip` (cu folderul `print-options/` la rădăcină);
4. publică un GitHub Release cu tag `v<versiune>` și atașează zip-ul.

Pluginul instalat citește `https://api.github.com/repos/sorinciupitu/print-options/releases/latest` și injectează actualizarea în WordPress. Când un release nou apare, **WordPress → Actualizări** (sau **Plugins**) afișează butonul de actualizare, fără intervenție manuală.

> Versiunea din header-ul din repo (ex. `1.7.0`) este doar baza `major.minor`. Numărul real al release-ului este adăugat la build, ca să nu fie nevoie de commit-uri automate de versionare (care ar produce conflicte la fiecare push).

Pentru a forța o verificare imediată: **Plugins → Print Options → Verifică actualizări**.

## Dezvoltare

```bash
php -l print-options.php
php -l includes/class-pov-quote.php
php -l includes/class-pov-updater.php

# zip local, pentru testare pe un site
php bin/build.php --version=1.7.0-dev --out=dist
```

Structura:

```
print-options.php                     plugin principal (opțiuni, prețuri, buton)
includes/class-pov-quote.php          pagina Cere ofertă, upload, CPT, admin
includes/class-pov-updater.php        update checker GitHub Releases
assets/quote.css | quote.js           designul și logica paginii Cere ofertă
assets/front.css                      stilurile butonului pe pagina produsului
assets/admin.js                       taburile din editorul de produs
bin/build.php                         generează print-options.zip
.github/workflows/release.yml         release automat la push
```

## Note

- Fișierele încărcate stau în `wp-content/uploads/` (public). Pentru fișiere confidențiale, restricționează accesul la uploads la nivel de server.
- Formularul are nonce, honeypot, limită de 6 cereri/oră/IP și validare de semnătură pentru EPS/AI/PSD.
