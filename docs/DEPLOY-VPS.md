# DTTL VPS deployment

Repository secrets (Settings → Secrets and variables → Actions):

| Name | Value |
| --- | --- |
| VPS_HOST | VPS IP or hostname, without https or aaPanel port |
| VPS_PORT | SSH port |
| VPS_USER | SSH account with write access to the DTTL site and /www/backup/dttl-deploy |
| VPS_SSH_KEY | Dedicated Actions private key, entire OpenSSH block, no passphrase |
| VPS_KNOWN_HOSTS | Verified VPS host key line, obtained locally on the VPS below |

The existing dttl_github deploy key authenticates VPS → GitHub. It is separate from the Actions → VPS key.

Run on the VPS as the chosen SSH user to create the Actions key and authorize its public half. This does not print the private key. Do not overwrite an existing key.

```bash
(
set -eu
umask 077
mkdir -p "$HOME/.ssh"
key_path="$HOME/.ssh/dttl_actions"
if [ ! -e "$key_path" ] && [ ! -e "$key_path.pub" ]; then
  ssh-keygen -t ed25519 -C dttl-github-actions -f "$key_path" -N ''
fi
test -s "$key_path.pub"
touch "$HOME/.ssh/authorized_keys"
chmod 700 "$HOME/.ssh"
chmod 600 "$HOME/.ssh/authorized_keys"
pub_key=$(cat "$key_path.pub")
if ! grep -qF -- "$pub_key" "$HOME/.ssh/authorized_keys"; then
  printf '\nrestrict %s\n' "$pub_key" >> "$HOME/.ssh/authorized_keys"
fi
echo 'Actions public key authorized.'
)
```

Copy the complete contents of `~/.ssh/dttl_actions` directly into VPS_SSH_KEY using aaPanel's file editor. Never paste it into chat, commits or screenshots. This key grants the SSH user's deployment rights; prefer a dedicated account when available.

Obtain VPS_KNOWN_HOSTS locally on the VPS, substituting the same HOST and PORT entered in Secrets:

```bash
read -r -p 'VPS_HOST: ' dttl_host
read -r -p 'VPS_PORT: ' dttl_port
if [ "$dttl_port" = 22 ]; then
  printf '%s ' "$dttl_host"
else
  printf '[%s]:%s ' "$dttl_host" "$dttl_port"
fi
cut -d ' ' -f 1,2 /etc/ssh/ssh_host_ed25519_key.pub
```

Choose Actions → DTTL - Deploy VPS → Run workflow on main to test SSH, site path, write permission and PHP. Manual runs never deploy files.

Pushes to main affecting wp-content deploy only the changed allowlisted files. The entire old theme is never copied. Each target must match its previous GitHub SHA-256 or already match the requested result. A mismatch stops the entire release before writing: reconcile the current live code with GitHub first. There is deliberately no force override. Deletions and symlinks are rejected.

PHP and JavaScript are checked before upload; PHP is checked again with the VPS PHP 8.3 binary. The site root is fixed to /www/wwwroot/daututuonglai.vn. Backups are stored outside the public webroot under /www/backup/dttl-deploy, including release.json listing newly added files. Write errors restore files already changed. This is file deployment, not an atomic whole-site switch; it does not promise automatic rollback for application-level errors. Inspect the live site after deployment; use the backup and release manifest for manual rollback when necessary.

No database, credentials, wp-config.php, uploads, cron settings or other websites are changed. Cache purge and plugin activation are not automatic. Failed deployment runs can be rerun; already matching files are skipped. Resolve a failed release before pushing another release, because each release covers only its own push diff.
