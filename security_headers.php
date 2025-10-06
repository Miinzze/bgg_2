<?php
// Security Headers
header("X-Frame-Options: SAMEORIGIN");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: geolocation=(self), camera=(self)");

// Content Security Policy
$csp = "default-src 'self'; ";
$csp .= "script-src 'self' 'unsafe-inline' https://unpkg.com https://cdnjs.cloudflare.com https://api.qrserver.com; ";
$csp .= "style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://unpkg.com; ";
$csp .= "img-src 'self' data: https: blob:; ";
$csp .= "font-src 'self' https://cdnjs.cloudflare.com; ";
$csp .= "connect-src 'self'; ";
header("Content-Security-Policy: " . $csp);