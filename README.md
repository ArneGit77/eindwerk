# eindwerk
https://trello.com/b/bpJSacnm/eindwerk

# SportFlow - Opstart Guide
 
Wat je doet als de Pi opnieuw is opgestart en je wil checken of alles draait.
 
## 1. Check of alles draait
 
```bash
sudo systemctl status apache2
sudo systemctl status mariadb
sudo systemctl status cron
```
 
Allemaal moeten "active (running)" tonen. Druk `q` om elke weergave af te sluiten.
 
## 2. Als iets niet draait, start het
 
```bash
sudo systemctl start apache2
sudo systemctl start mariadb
sudo systemctl start cron
```
