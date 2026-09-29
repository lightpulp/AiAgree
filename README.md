# Basecamp
    php tools/simulate.php                 # decoder test + fake data
    php -S 0.0.0.0:8080 -t public          # dashboard at http://localhost:8080
    curl -X POST -H "X-Token: change-me" -d "0401002A25011C01384A520C91" http://localhost:8080/receive.php
Change the token in config.php. pH = byte / 20. Temps are signed int16 in 0.1 °C.
