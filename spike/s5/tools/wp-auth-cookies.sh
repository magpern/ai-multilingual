#!/usr/bin/env bash
#
# Emit WordPress auth cookies for browser automation (avoids login form / CF).
# THROWAWAY. Branch spike/s5 only.
set -euo pipefail

USER_ID="${1:-1}"
WORDPRESS_COMPOSE_DIR="/opt/biopentra/apps/wordpress"

cd "$WORDPRESS_COMPOSE_DIR"
docker compose run --rm -T wpcli wp eval "
\$user_id = (int) ${USER_ID};
\$expiration = time() + DAY_IN_SECONDS;
\$token = wp_get_session_token();
\$manager = WP_Session_Tokens::get_instance( \$user_id );
\$verifier = \$manager->create( \$expiration );
\$auth = wp_generate_auth_cookie( \$user_id, \$expiration, 'auth', \$verifier );
\$logged_in = wp_generate_auth_cookie( \$user_id, \$expiration, 'logged_in', \$verifier );
echo wp_json_encode( array(
  'user_id' => \$user_id,
  'auth' => \$auth,
  'logged_in' => \$logged_in,
  'secure_auth' => wp_generate_auth_cookie( \$user_id, \$expiration, 'secure_auth', \$verifier ),
) );
" 2>/dev/null
