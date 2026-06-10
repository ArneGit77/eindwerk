# SportFlow

SportFlow is een fitness app die op een website werkt en die draait op een Raspberry Pi 5. Met SportFlow kunnen sporters hun trainingen, hun gewicht en hun doelen bijhouden. De app toont ook grafieken van hoe je vooruitgaat.


## Doel van de applicatie

SportFlow is gemaakt voor mensen die graag sporten en hun progressie willen bijhouden. De gebruiker kan:

- Inloggen met een eigen account
- Trainingen toevoegen van verschillende soorten sporten (krachttraining, cardio, teamsporten, andere)
- Bij krachttraining meerdere oefeningen per sessie invullen met sets, reps en gewicht
- Zijn gewicht bijhouden over tijd
- Doelen instellen (aantal trainingen per week, minuten per week, doelgewicht)
- Statistieken bekijken via grafieken

Elke gebruiker ziet enkel zijn eigen gegevens. Niemand kan bij data van een andere gebruiker.


## Hoe gebruik je de app?

### Account aanmaken

Klik op de inlogpagina onderaan op het registratieformulier. Kies een gebruikersnaam en een wachtwoord van minstens 6 tekens. Bevestig het wachtwoord en klik op "Account aanmaken". Daarna kan je inloggen.

### Een training toevoegen

Ga naar de pagina "Training plannen". Bovenaan staat een formulier.

1. Kies de datum van de training.
2. Kies het type training uit de dropdown (kracht, cardio, team of andere).
3. Vul de duur in minuten in.
4. Afhankelijk van het type training verschijnen extra velden. Bij cardio bijvoorbeeld afstand en calorieën. Bij krachttraining kan je oefeningen toevoegen via de plus knop.
5. Klik op "Training opslaan".

De training verschijnt nu in de tabel eronder. Door op een training te klikken klap je de details open.

### Je gewicht bijhouden

Op de homepage staat onderaan een formulier om je gewicht in te voeren. Vul de waarde in en klik op "Opslaan". Het nieuwe gewicht verschijnt direct in de kaart bovenaan en in de geschiedenis.

### Doelen instellen

Ook op de homepage kan je je doelen instellen:

- Aantal trainingen per week
- Aantal minuten per week
- Doelgewicht

### Statistieken bekijken

Ga naar de pagina "Statistieken". Bovenaan kan je kiezen welke periode je wil zien: laatste week, laatste maand of alles.


## Installatie-instructies

Hieronder leg ik uit hoe je SportFlow op een Raspberry Pi installeert.

### Wat je nodig hebt

- Een Raspberry Pi (versie 4 of 5)
- Een micro-SD-kaart van minstens 16 GB
- Een internetverbinding op de Pi
- Een computer om commando's uit te voeren via SSH

### Stap 1: Raspberry Pi OS installeren

Installeer Raspberry Pi OS op je SD-kaart via de Raspberry Pi Imager. Steek de SD-kaart in de Pi en start hem op.

### Stap 2: Software installeren

Open een terminal op de Pi (of verbind via SSH). Voer dan deze commando's uit:

```bash
sudo apt update
sudo apt install apache2
sudo apt install php libapache2-mod-php php-mysql
sudo apt install mariadb-server
```

Dit installeert de webserver Apache, PHP en de database MariaDB.

### Stap 3: Project binnenhalen

Clone de repository in de map /var/www:

```bash
cd /var/www
sudo git clone https://github.com/ArneGit77/eindwerk.git
sudo mv eindwerk/Website /var/www/Website
```

### Stap 4: Database aanmaken

Voer het SQL-bestand uit om de database aan te maken:

```bash
sudo mysql < /var/www/Website/database/SportFlowDatabase.sql
```

### Stap 5: Apache configureren

Pas het Apache-configuratiebestand aan zodat het naar /var/www/Website wijst:

```bash
sudo nano /etc/apache2/sites-available/000-default.conf
```

Wijzig in dat bestand:

```
DocumentRoot /var/www/Website
```

Herstart Apache:

```bash
sudo systemctl restart apache2
```

### Stap 6: Permissies goed zetten

```bash
sudo chown -R www-data:www-data /var/www/Website
sudo chmod -R 755 /var/www/Website
```

### Stap 7: Cron job voor systeemstatistieken

Voeg deze regel toe aan crontab via `sudo crontab -e`:

```
*/15 * * * * /var/www/Website/scripts/update_stats.sh >> /var/log/sportflow_stats.log 2>&1
```

### Stap 8: Testen

Open een browser en surf naar het IP-adres van de Pi. Je zou de inlogpagina van SportFlow moeten zien.


## GDPR-maatregelen

SportFlow houdt persoonlijke gegevens bij zoals gewicht en trainingen. Daarom gelden de volgende regels.

Er worden enkel gegevens gevraagd die echt nodig zijn: een gebruikersnaam, een wachtwoord en de info die de gebruiker zelf invoert. Geen e-mailadres of geboortedatum. Er gebeurt niets stiekem op de achtergrond. Geen tracking-cookies en geen delen met externe partijen.

De gebruiker kan op elk moment zijn eigen data bekijken, trainingen verwijderen of zijn doelen aanpassen. Wachtwoorden zijn versleuteld met bcrypt en gegevens van de ene gebruiker zijn nooit zichtbaar voor een andere.