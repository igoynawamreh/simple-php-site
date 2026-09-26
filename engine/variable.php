<?php

// Find out the folder where this application is located ($base_url).
//   Example: if the app is at "/myapp/index.php" -> $base_url = "/myapp"
//   Example: if the app is at the domain root    -> $base_url = "" (empty)
$base_url = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');

// Get the full path the user typed, WITHOUT the query string (?id=5 etc).
//   Example: "site.com/blog/5?ref=fb" -> $path = "/blog/5"
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Strip $base_url from the front of $path if present, leaving just the "route".
// Also trim leading/trailing slashes (/) to keep it clean.
//   Example: "/blog/5/"          -> $route = "blog/5"
//   Example: "/myapp/blog/5.php" -> $route = "blog/5.php"
$route = trim(substr($path, strlen($base_url)), '/');
// If the route still has a ".php" suffix (e.g. someone accessed the file directly),
// remove it so the URL stays clean.
//   Example: "/profil.php"            -> $route = "profil"
//   Example: "/myapp/admin/index.php" -> $route = "admin/index"
$route = preg_replace('/\.php$/', '', $route); // strip .php

// Determine the scheme used: "http" or "https".
// Check HTTPS: if the HTTPS header is set and not "off", it's https.
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';

// Get the FULL domain name including its extension.
// HTTP_HOST usually already includes the port if it's not the default (e.g. "site.com:8080").
//   Example: "https://www.site.com"  -> "www.site.com"
//   Example: "https://site.com"      -> "site.com"
//   Example: "https://site.com:8080" -> "site.com:8080"
$host = $_SERVER['HTTP_HOST'];

// Strip the port if present, by cutting the string before ":".
//   Example: https://www.site.com:8080 -> "www.site.com"
$domain = strtok($host, ':');
// Strip leading "www." if present
$domain = preg_replace('/^www\./i', '', $domain);

// Absolute URL to the app's homepage, including scheme, host, and $base_url.
// Use $host (not $domain) so non-standard ports are kept,
// and so the host matches exactly what the user used — if "www." were stripped,
// all links would become cross-origin and the session/cookie could be lost.
//   Example: app at root         -> "https://site.com"
//   Example: app at /myapp       -> "https://site.com/myapp"
//   Example: dev on port 8080    -> "http://localhost:8080/myapp"
$home_url = $scheme . '://' . $host . $base_url;
