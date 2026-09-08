# PowerUI und widgetspezifische UI Design Prinzipien

## Datentabellen

### Zweck von Datentabellen

Datentabellen eignen sich, um strukturierte Informationen in Zeilen und Spalten darzustellen. Sie helfen Nutzern, Daten schnell zu erfassen, zu vergleichen, zu verwalten und mit ihnen zu interagieren.
Eine Datentabelle ist geeignet, wenn:

* Informationen klar tabellarisch organisiert sind
* mehrere Datensätze verglichen oder bearbeitet werden müssen
* Attribute über mehrere Zeilen hinweg relevant sind

Eine Datentabelle ist weniger geeignet für:

* unstrukturierte Inhalte
* lange Fließtexte
* multimediale Inhalte
* sehr wenige Einträge
* einzelne Datensätze
* stark visuelle Inhalte
* komplexe Interaktionen
* hierarchische Strukturen
* Trenddarstellungen
* kleine mobile Ansichten

***

### PowerUI-spezifische Design Prinzipien für Datentabellen

* Häufig benötigte Filter sollten außerhalb der Tabelle direkt sichtbar angeboten werden.
* Filter außerhalb der Tabelle sollen dieselbe Reihenfolge haben wie die Spalten in der Tabelle. Die relative Position zur Spalte ist dabei nicht entscheidend.
* Spalten, die den wichtigsten Status anzeigen, sollen „Status“ heißen.
* Wenn Status als Text angezeigt wird, muss die Spalte breit genug sein, damit der längste Statustext vollständig lesbar bleibt.
* Texte von Status müssen den nächsten Prozessschritt erkennbar machen und handlungsorientiert sein. Schlechtes Beispiel für eine Status-Bezeichnung: "Fertiggestellt", besser: "Zu prüfen".
* Spaltennamen dürfen nicht mehrfach vokommen.
* Spaltennamen müssen so gewählt werden, dass kein unnötier Weißraum entsteht.

***

### Best Pracitces für Datentabellen

#### 1\. Struktur und Verständlichkeit

* Daten sind klar in Zeilen und Spalten organisiert
* Die Reihenfolge der Spalten ist fachlich sinnvoll, sodass die wichtigsten Informationen an erster Stelle stehen
* Spaltenüberschriften sind verständlich und eindeutig
* Tooltips sind gepflegt, verständlich und so kurz wie möglich gehalten

#### 2\. Lesbarkeit

* Spaltenbreiten sind angemessen 
* Inhalte lassen sich schnell scannen
* Inhalte sind sinnvoll gekürzt oder umgebrochen
* Dialog ist nicht mit Informationen überladen

#### 3\. Datenvergleich

* Datensätze können einfach verglichen werden
* Ähnliche Werte sind konsistent formatiert
* Zahlen, Texte und Statuswerte sind fachlich so ausgerichtet, dass Werte, die in Bezug zueinander stehen, nah aneinander platziert sind

#### 4\. Sortierung und Filterung

* Sichtbare Sortier- oder Filterfunktionen sind vorhanden, wenn relevant
* Filter sind sinnvoll platziert, zum Beispiel in der gleichen Reihenfolge, wie die Spalten in der dazugehörigen Datentabelle

#### 5\. Interaktion

* Zeilenaktionen oder Tabellenaktionen sind erkennbar
* Bearbeitungsmöglichkeiten sind klar erkennbar
* Die Interaktionen passen zum Zweck der Tabelle

#### 6\. Komplexität

* Die Tabelle ist übersichtlich und nicht überladen
* Es gibt nur fachlich relevante Spalten in der Standardansicht
* Alle sichtbaren Informationen sind für den direkten Vergleich notwendig

Eine hohe Informationsdichte ist akzeptabel, wenn viele Datensätze und Attribute direkt verglichen werden müssen.
Wenn Informationen für den direkten Vergleich nicht nötig sind, sollten sie ausgelagert werden, um Komplexität zu reduzieren.

***

## Buttons

### Zweck von Buttons

Buttons bieten Nutzern klar erkennbare Aktionen an. Sie steuern Workflows, führen Aufgaben aus und helfen, Prozesse abzuschließen.

***

### PowerUI-spezifische Design Prinzipien für Buttons

Buttons oberhalb einer Tabelle sollen:

* ein verständliches Icon haben
* eine prägnante, handlungsorientierte Beschriftung haben
* innerhalb derselben Seite nicht dieselbe Beschriftung wie ein anderer Button verwenden
* bei kritischen Aktionen nicht sofort auslösen, sondern zuerst ein Bestätigungs-Pop-up anzeigen

Nicht klickbare Buttons werden in PowerUI ausgegraut und nicht versteckt.

***

### Standard-Buttons in PowerUI

Folgende PowerUI-Konventionen gelten für Buttons:

| Aktion | Button-Label | Icon / PowerUI-Wert | Verwendung |
| ------ | ------------ | ------------------- | ---------- |
| Neuen Datensatz anlegen | Neu | `plus` | Wenn neue Datensätze erzeugt werden |
| Details anzeigen | Detail | `info-circle` | Wenn keine Bearbeitung möglich ist |
| Datensatz bearbeiten | Bearbeiten | `pencil-square-o` | Wenn ein Bearbeitungsdialog geöffnet wird |
| Bearbeitung abschließen | Fertigstellen | Klemmbrett mit Haken | Wenn eine Bearbeitung abgeschlossen wird |
| Datensatz final freigeben | Freigeben | `check` | Wenn ein Datensatz abschließend freigegeben wird |

Ein Button darf von diesen Konventionen abweichen, wenn dadurch Verwirrung vermieden wird, zum Beispiel wenn sonst zwei Buttons auf derselben Seite „Bearbeiten“ heißen würden.

Die angegebenen Icons sind spezifisch für die angegebenen Buttons vorbehalten. Andere Buttons, wie z. B. umbenennen dürfen nicht das Icon von Bearbeiten benutzen.

***

### Button-Menüs

Buttons können in PowerUI über ein Button-Menü gruppiert werden:
`PowerUI widget_type: menubutton`
Button-Menüs eignen sich für:

* ähnliche Funktionen
* Nebenaktionen
* selten verwendete Aktionen
* Reduktion visueller Komplexität

Häufig verwendete Hauptaktionen sollten nicht in Button-Menüs versteckt werden, da dadurch zusätzliche Klicks entstehen.

***

### Best Practices für Buttons

Ein guter Button:

* beschreibt eine klare Aktion
* hat ein eindeutiges Label
* ist visuell als klickbar erkennbar
* hat ausreichend Abstand zu anderen klickbaren Elementen
* ist entsprechend seiner Wichtigkeit gestaltet
* konkurriert nicht mit zu vielen anderen Buttons
* ist im Kontext der Seite verständlich
* macht deutlich, ob eine Aktion sicher, kritisch oder abschließend ist

Gute Labels sind handlungsorientiert, zum Beispiel:

* Speichern
* Absenden
* Abbrechen
* Datei hochladen
* Details anzeigen

Schlechte oder zu vage Labels sind zum Beispiel:

* Hier klicken
* Mehr
* Ok
* Weiter
* Bearbeitung statt Bearbeiten

Wenn es eine Hauptaktion in einem Dialog gibt, sollte sie in PowerUI mit folgender Property hervorgehoben werden:
`visibility: promoted`

***