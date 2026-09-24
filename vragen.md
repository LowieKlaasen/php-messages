## 2. Data-ophaling

### In de backend status voorzien of afleiden in frontend?

Aangezien ik ook gewerkt heb met een 'rejected' status heb ik ervoor gekozen om te werken met een expliciete status als mijn single source of truth, de timestamps zijn voor logging doeleinden.

### Hoe omgaan met kleine verschillen tussen queries voor verschillende use cases?

Voor het ophalen van verschillende berichten op basis van status heb ik gekozen voor een filter, omdat de achterliggende logica hetzelfde blijft. Voor de gelijkaardige endpoints voor het verifiëren
en verzenden van berichten heb ik wel voor verschillende endpoints gekozen omdat het hier om totaal verschillende business acties gaat.

### Wanneer kies je voor hergebruik en wanneer is duplicatie oké?

Mijn antwoord hier komt overeen met het vorige antwoord. Wanneer het om verschillende business acties gaat zorgt duplicatie voor duidelijkere code.
