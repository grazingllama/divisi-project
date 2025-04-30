## divisi

divisi ist ein Projekt zur Musikvermittlung von Chor- und Orchesterwerken.


## Einsatzgebiet und Funktionalität

Die Web-Anwendung divisi adressiert eine zentrale Herausforderung in der Vermittlung klassischer Musik: das Verständnis komplexer musikalischer Strukturen in Chor- und Orchesterwerken. Während traditionelle Aufnahmen alle Stimmen in einer festgelegten Mischung präsentieren, ermöglicht divisi es den Nutzern, die Zusammensetzung des Klangs interaktiv zu erkunden. Diese digitale Lösung eröffnet völlig neue Möglichkeiten für das Musikverständnis und die musikalische Bildung.

Im musikwissenschaftlichen Kontext können Dozenten und Studierende nun Aspekte wie Stimmführung, kontrapunktische Techniken oder Instrumentationsdetails unmittelbar hörbar machen. Die Möglichkeit, einzelne Stimmen hervorzuheben oder in den Hintergrund treten zu lassen, unterstützt die analytische Auseinandersetzung mit dem musikalischen Material und macht komplexe Strukturen auch für Laien nachvollziehbar.

Für die musikpraktische Arbeit bietet divisi wertvolle Unterstützung bei der Probenvorbereitung. Chorsänger und Instrumentalisten können ihre eigene Stimme im Gesamtkontext üben, indem sie diese besonders hervorheben, während die anderen Stimmen als Orientierung im Hintergrund bleiben. Dies ermöglicht ein effizientes Einstudieren neuer Werke und fördert das Verständnis für die eigene Rolle im Ensemble.

Im musikpädagogischen Bereich schafft die Anwendung neue Zugänge zur klassischen Musik. Lehrkräfte können im Unterricht musikalische Phänomene wie Themeneinsätze, Instrumentenfamilien oder Satztechniken anschaulich demonstrieren. Die intuitive Bedienung ermöglicht es auch Schülern, selbstständig auf Entdeckungsreise durch die Werke zu gehen und ihr Gehör für musikalische Strukturen zu schulen.

Gegenüber traditionellen Vermittlungsformen bietet diese digitale Lösung entscheidende Vorteile: Sie ermöglicht ein aktives, exploratives Lernen, ist jederzeit und überall verfügbar und kann flexibel an verschiedene Lernszenarien angepasst werden. Die Kombination aus hochwertigem Audiomaterial und interaktiver Steuerung schafft dabei eine Brücke zwischen theoretischem Wissen und praktischer Hörerfahrung.



## Technische Spezifikation

Die Grundlage für die Entwicklung bildet eine klare Definition der erwarteten Eingaben und gewünschten Ausgaben. Diese funktionale Spezifikation beschreibt, was das System leisten soll, ohne bereits festzulegen, wie dies technisch umgesetzt wird.

### Erwartete Eingaben

**Audiomaterial:**
- Mehrspur-Aufnahmen klassischer Werke
- Eine Stereo-Gesamtaufnahme als Referenz
- Separate Mono-Aufnahmen aller Einzelstimmen
- Einheitliche Länge und Synchronisation aller Spuren

**Metadaten:**
- Werkangaben (Titel, Komponist, Entstehungszeit, Katalognummer)
- Aufführende (Ensemble, Dirigent, Solisten)
- Aufnahmedaten (Datum, Ort, Copyright)
- Strukturelle Informationen (Instrumentierung, Besetzung)

**Benutzerinteraktionen:**
- Lautstärkeregelung für Stimmen und Gruppen
- Gruppierungsdefinitionen für Instrumente
- Wiedergabesteuerung
- Sprachauswahl
- Qualitätseinstellungen

### Erwartete Ausgaben

**Audiowiedergabe:**
- Gemischtes Stereosignal aller Stimmen
- Individuell regelbare Stimmlautstärken
- Verschiedene Wiedergabequalitäten

**Benutzeroberfläche:**
- Steuerelemente für die Audiowiedergabe
- Anzeige der aktiven Stimmen/Gruppen
- Darstellung der Werkinfos
- Sprachabhängige Beschriftungen

**Systemmeldungen:**
- Lade- und Verarbeitungszustände
- Fehler- und Statusmeldungen
- Systemzustandsinfos

Diese funktionale Spezifikation bildet die Grundlage für die nachfolgende Entwicklung der Systemarchitektur und die konkrete technische Implementierung.



## Lösungsweg und Systemaufbau

Die Anwendung gliedert sich in mehrere logische Einheiten, die miteinander interagieren, um die gewünschte Funktionalität bereitzustellen. Im Folgenden wird der konzeptionelle Aufbau beschrieben.

### Zentrale Komponenten

**1. Medienverwaltung**
- Verarbeitet hochgeladene Audio-Dateien
- Organisiert die Speicherung von Aufnahmen und Metadaten
- Stellt Audiodaten in verschiedenen Qualitätsstufen bereit
- Verwaltet zusätzliche Medien wie Bilder

**2. Datenbankschicht**
- Speichert alle Werk- und Aufführungsinformationen
- Verwaltet Beziehungen zwischen Werken, Künstlern und Aufnahmen
- Organisiert Besetzungs- und Gruppierungsinformationen
- Ermöglicht mehrsprachige Inhalte

**3. Audioverarbeitung**
- Empfängt Stereo- und Einzelspuraufnahmen
- Verarbeitet Audiodaten für die Wiedergabe
- Steuert die Lautstärke einzelner Spuren
- Mischt die Spuren zum finalen Ausgabesignal

**4. Benutzeroberfläche**
- Zeigt verfügbare Aufnahmen und deren Details
- Stellt Bedienelemente für die Wiedergabe bereit
- Ermöglicht die Steuerung der Spurenlautstärken
- Bietet Zugriff auf alle Systemeinstellungen

### Datenaustausch und Interaktionen

**Aufnahme-Upload:**
```pseudocode
WENN neue Aufnahme hochgeladen:
    Prüfe Dateiformate und Vollständigkeit
    Speichere Metadaten in Datenbank
    Verarbeite Audiodateien
    Erstelle verschiedene Qualitätsstufen
    Bestätige erfolgreichen Upload
```

**Wiedergabe-Steuerung:**
```pseudocode
WENN Aufnahme ausgewählt:
    Lade Metadaten aus Datenbank
    Initialisiere Audio-Engine
    Lade Audiospuren in gewählter Qualität
    Stelle Benutzeroberfläche bereit
    Aktiviere Wiedergabe-Kontrollen
```

**Lautstärke-Regelung:**
```pseudocode
WENN Lautstärke geändert:
    Identifiziere betroffene Spur/Gruppe
    Berechne neue Lautstärkewerte
    Aktualisiere Audio-Mix
    Zeige neue Einstellung in UI
```

### Datenfluss

1. **Eingabeverarbeitung**
   - Benutzerinteraktionen werden erfasst
   - Systemeinstellungen werden angewendet
   - Audiodaten werden geladen

2. **Zentrale Verarbeitung**
   - Audiodaten werden gemischt
   - Metadaten werden aufbereitet
   - Benutzereinstellungen werden gespeichert

3. **Ausgabegenerierung**
   - Audioausgabe wird erzeugt
   - Benutzeroberfläche wird aktualisiert
   - Systemmeldungen werden generiert

### Prozessabläufe

**Start einer Wiedergabe:**
```pseudocode
1. Benutzer wählt Aufnahme
2. System lädt Metadaten
3. Audio-Engine initialisiert Spuren
4. Benutzeroberfläche wird aktualisiert
5. Wiedergabe beginnt
```

**Änderung der Spurenlautstärke:**
```pseudocode
1. Benutzer verstellt Regler
2. System berechnet neue Werte
3. Audio-Engine passt Mixtur an
4. Benutzeroberfläche zeigt Änderung
```

Diese konzeptionelle Beschreibung bildet die Grundlage für die nachfolgende technische Implementierung, bei der konkrete Technologien und Programmiersprachen zum Einsatz kommen.