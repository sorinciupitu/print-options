=== Print Options for WooCommerce ===
Contributors: sorinciupitu
Tags: woocommerce, product options, quote request, print shop
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.9.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Opțiuni vizuale, cantități și prețuri pentru produsele WooCommerce, plus pagina „Cere ofertă” cu buton pe orice produs.

== Description ==

Pentru tipografii și magazine cu produse configurabile:

* cantitate minimă și pas de comandă;
* prețuri fixe per unitate/comandă sau grilă cantitativă;
* grupuri de opțiuni afișate ca carduri;
* secțiune Grafică cu încărcare PDF/EPS și serviciu de design;
* buton **Cere ofertă** per produs, care deschide o pagină de cerere de ofertă cu produsul completat automat;
* butonul funcționează și pentru produsele fără preț (produse complexe, care se comandă doar pe bază de ofertă);
* cererile sunt salvate în WooCommerce → Cereri ofertă și trimise pe email;
* actualizare automată din GitHub Releases.

== Installation ==

1. Instalează și activează pluginul.
2. La activare se creează pagina „Cere ofertă” cu shortcode-ul `[pov_quote_form]`.
3. În editorul produsului, tab **Cerere ofertă**, bifează „Afișare buton Cere ofertă”.

== Frequently Asked Questions ==

= Pot edita pagina Cere ofertă? =
Da. Titlul și conținutul din jurul shortcode-ului sunt editabile din Pagini → Cere ofertă.

= Unde ajung cererile? =
În WooCommerce → Cereri ofertă și pe emailul configurat în aceeași pagină.

== Changelog ==

= 1.9.0 =
* Shortcode `[pov_quote_button]` pentru plasarea butonului oriunde (descriere produs, Elementor, widget HTML).
* Butonul se adaugă automat la finalul descrierii produselor fără preț, dacă tema nu afișează formularul de comandă.
* Atribute: `id`, `label`, `force`.

= 1.8.0 =
* Butonul „Cere ofertă” apare și pe produsele fără preț, acolo unde WooCommerce nu afișează formularul „Adaugă în coș”.
* Buton restilizat: fundal #DA0068, icon și text albe.

= 1.7.0 =
* Nou: bifă per produs „Afișare buton Cere ofertă”.
* Nou: pagina „Cere ofertă” cu produse multiple, descriere, cantitate, încărcare fișiere și date de contact.
* Nou: listă Cereri ofertă în dashboard, cu status și fișiere.
* Nou: actualizare automată din GitHub Releases.

= 1.6.0 =
* Îmbunătățiri la prețul flotant și la șabloanele de produs.

== Upgrade Notice ==

= 1.9.0 =
Adaugă shortcode-ul pentru buton și afișarea automată la produsele fără preț.

= 1.8.0 =
Butonul Cere ofertă apare acum și la produsele fără preț.

= 1.7.0 =
Adaugă butonul și pagina „Cere ofertă”.
