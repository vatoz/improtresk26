# Zadávací dokumentace – Improtřesk 2026

## 1. Úvod

**Název projektu:** Improtřesk 2026

**Cíl projektu:** Vytvořit web malého festivalu s možností online registrace účastníků na workshopy a doprovodný program.

**Primární publikum:** Účastníci festivalu (cca stovky lidí), organizátor (1 administrátor).

**Použití:** Web má být využit i pro další ročníky.

---

## 2. Obsah webu

### 2.1 Veřejná část

- **Úvodní stránka:** stručné info (co, kdy, kde), tlačítko „Zaregistruj se"
- **Program festivalu:** přehled hlavních akcí a časového harmonogramu
- **Workshopy:** seznam cca 10 workshopů (popis, lektor, kapacita, obsazenost)
- **Informace:** místo konání, doprava, ubytování, ceny vstupného
- **FAQ**
- **Kontakty**

### 2.2 Uživatelská část (po přihlášení)

- **Registrace účtu:** jméno, e-mail, heslo (s možností obnovy)
- **Přihlášení**
- **Přihláška na festival:**
  - výběr 1 workshopu (max. kapacita 12 účastníků na workshop)
  - možnost označit doprovodný program (volitelně, až bude připraven)
- **Platební údaje:** vygenerovaný QR kód pro platbu (bankovní převod), textově i číslo účtu/variabilní symbol
- **Profil účastníka:** přehled vybraných možností, možnost úprav
- **Notifikace:** potvrzení registrace e-mailem

---

## 3. Administrace

- Přehled a správa účastníků
- Přidávání a editace workshopů a programu
- Nastavení kapacit workshopů
- Export účastníků do CSV/Excelu
- Správu provádí pouze **1 administrátor**

---

## 4. Design a UX

- **Responzivní** (mobil/tablet/PC)
- Jednoduchý, moderní, snadno upravitelný design
- Branding se doplní až později (barvy, logo, grafika)

---

## 5. Technické požadavky

- **Hosting:** vlastní
- **Technologie:** PHP + MySQL/MariaDB
- **Bezpečnost:** šifrovaná hesla, HTTPS, GDPR (uchování osobních údajů účastníků)
- **Platební systém:** generování QR plateb + uvedení bankovních údajů
- **Rozšiřitelnost:** možnost úprav pro další ročníky

---

## 6. Rozpočet

Vytváří organizátor sám (s využitím AI), rozpočet na web = **0 Kč**.

---

## 7. Poznámka

Zároveň si hraju s AI co mi pomůže web vytvořit.
