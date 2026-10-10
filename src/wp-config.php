<?php

/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the website, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define('DB_NAME', 'learncommerce');

/** Database username */
define('DB_USER', 'wpuser');

/** Database password */
define('DB_PASSWORD', 'password');

/** Database hostname */
define('DB_HOST', 'db');

/** Database charset to use in creating database tables. */
define('DB_CHARSET', 'utf8mb4');

/** The database collate type. Don't change this if in doubt. */
define('DB_COLLATE', '');

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define('AUTH_KEY',         '&(#IU:zK:&okf,~; bt,i+z*s,{ixWkR[dRQCgCB[+&!WU X:.W`g6f%^bqW7X84');
define('SECURE_AUTH_KEY',  'e5Atpg*Fp<0cRvS:z4ZtcZ5fzps2m8/5qme4`d.*PN}hy~~j.A()*F(8p2;MIHEf');
define('LOGGED_IN_KEY',    'Adv,Xv^M)hQoRf~U:yhKD;>q]05pX<(Pcv+v@ORCz;M=+Ntla:Bc![ pum8+r7Ls');
define('NONCE_KEY',        ';3/TzF,lt$b)06f$2;JF2e{>[X4hE&sW%-Ssg$YV<7:N!&]ErY09=dKN_U4Cmt<&');
define('AUTH_SALT',        'BZBe*QLyiW.#q3FBA6ABE>f]XRa=.9S&=?%yo?Rg9WJ1]RUKJd<vKwoUQFU@,HNH');
define('SECURE_AUTH_SALT', 'J49Ims!mYcqLJo_ UGZ4<#dMwmZJen~ 6>A(;dsr><7g8,=Yd^I)R1Gi}i2(Jc18');
define('LOGGED_IN_SALT',   '*E+pSs:?jN-G{a2)c@@kZ4QxtXDg9`9c0-aZjoy*!i[c?4{-I}|MlF0!NK~s,WPe');
define('NONCE_SALT',       's41iqc`o^V+%ca(Kwx&,Z/!FJa=<XSk$@Nm^,9oTRK%?864Ah&zaeOPmtVH*?hA4');

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 *
 * At the installation time, database tables are created with the specified prefix.
 * Changing this value after WordPress is installed will make your site think
 * it has not been installed.
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/#table-prefix
 */
$table_prefix = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/
 */
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if (! defined('ABSPATH')) {
	define('ABSPATH', __DIR__ . '/');
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
