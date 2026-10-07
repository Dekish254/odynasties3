TEMPORARY DIAGNOSTIC BUILD

This build changes /health.php temporarily so Render can report which MySQL server
it reaches and which databases/tables it can see. It also changes render.yaml DB_PORT
to the Railway public port 33553.

After the diagnostic is complete, restore the normal health.php before keeping the site live.
