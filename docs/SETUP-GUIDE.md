# Games Site Setup Guide

Step-by-step roadmap for everything involved in publishing a browser game:
local tools, GitHub, the build workflow, and Plesk hosting. Each section is
self-contained so you can jump straight to the step you need.

This file is in a public repo. Never put tokens, passwords, or the Plesk
webhook URL in it.

Menu names below come from the GitHub and Plesk screens as we used them.
Plesk and GitHub rename things occasionally, so treat labels as close, not exact.

## 0. Key facts

| Thing | Value |
|---|---|
| GitHub account | `hingohongo-ai` |
| Plesk panel | https://plesk24.xmission.com:8443 (Websites & Domains: https://plesk24.xmission.com:8443/smb/web/view) |
| Site domain | `skeletonbelly.com` (also `www.skeletonbelly.com`) |
| Old temp domain | `hungry-jang.166-70-198-108.plesk.page` (replaced by the site domain; see section 11) |
| Server IP | `166.70.198.108` |
| Name servers | `ns.xmission.com`, `ns1.xmission.com` |
| Local repos folder | `C:\Users\hingo\source\repos` |
| Front page repo | `baseCamp` (deploys to the domain root) |
| Game repos | one per game, e.g. `asteroidDoge`, each deploys to its own subfolder |
| Main branch name | `master` (not `main`) |
| Published branch | `deploy` (built files only, written by the GitHub Action) |
| Old redirect domain | `differentminds-wi.com` (website hosting + .htaccess redirect). Never deploy into it. |

Host limits we found:
- SSH is Forbidden, so everything goes through the Plesk UI (File Manager, Git).
- PHP is not offered (only FastCGI, CGI, SSI). Games must be static.
- Node.js 21.7.3 exists in Plesk but is not enabled; not needed for static games.
- HTTPS works (free Let's Encrypt certificate, section 11). 20 GB disk, unlimited traffic.

## 1. One-time machine setup

1. Install Node.js (it includes npm): download the **LTS** installer from https://nodejs.org
   and accept the defaults, or run `winget install OpenJS.NodeJS.LTS`.
2. Install Git for Windows from https://git-scm.com if `git --version` fails.
3. Close and reopen your terminal, then check:
   ```
   node --version
   npm --version
   git --version
   ```

## 2. GitHub access token (fine-grained PAT)

HTTPS pushes need a token, not your password.

1. Sign in to GitHub as `hingohongo-ai`.
2. Click your profile picture (top right) -> **Settings**.
3. Scroll to the very bottom of the left sidebar -> **Developer settings**.
   Direct link: https://github.com/settings/personal-access-tokens
   (This is account settings, not a repo's Settings tab.)
4. **Fine-grained tokens** -> **Generate new token**.
5. Repository access: choose the repos it should reach. For many games, pick
   **All repositories**, or edit the token later to add each new repo.
6. Repository permissions: **Contents: Read and write** and
   **Workflows: Read and write**. Without Workflows, any push containing
   `.github/workflows/*.yml` is rejected.
7. Set an expiration you will remember, then generate and copy the token.
   Store it in your password manager. It is shown once.

To add a repo to an existing token: Developer settings -> Fine-grained tokens ->
click the token -> Repository access -> add the repo -> Save.

### First push quirk (Windows)

Git for Windows may prompt for only the token (no username). Paste the token
and the push proceeds. If it keeps sending a wrong saved credential, open
Windows **Credential Manager -> Windows Credentials**, remove entries starting
with `git:https://github.com`, and push again.

## 3. Create a new game project

1. Pick a name (e.g. `asteroidDoge`). Create the folder under
   `C:\Users\hingo\source\repos`.
2. Files (copy from an existing game such as `asteroidDoge`):
   ```
   index.html
   src/main.js, src/style.css
   vite.config.js          must contain  base: './'
   package.json            vite as a devDependency
   .gitignore              node_modules/ and dist/
   README.md
   .github/workflows/deploy.yml
   ```
3. `base: './'` in `vite.config.js` is what lets a game work from any subfolder
   or domain. Do not remove it.
4. In the project folder:
   ```
   npm install
   npm run dev
   ```
   Open the printed address (usually http://localhost:5173) to play.
5. `npm run build` makes the static site in `dist/`.

## 4. Create the GitHub repo and push

1. Make sure the token from section 2 covers the new repo.
2. Go to https://github.com/new. Owner `hingohongo-ai`, name = the folder name,
   **Public**, and leave README, .gitignore and license **unchecked**. Create.
3. In the project folder:
   ```
   git init -b master
   git remote add origin https://github.com/hingohongo-ai/<repo>.git
   git add .
   git commit -m "Initial commit"
   git push -u origin master
   ```
4. After the first push, check repo **Settings -> Branches** and make
   `master` the default branch if GitHub chose something else.

## 5. Check the build (GitHub Actions)

1. Open the repo's **Actions** tab. The run is named after the `name:` line in
   `deploy.yml` (for example "Build and publish deploy branch"). Wait for green.
2. On the **Code** tab, open the branch dropdown and confirm a **`deploy`** branch
   exists. It holds only the built site, no source.
3. If the run fails with a permissions error pushing `deploy`, check that
   `deploy.yml` has `permissions: contents: write`.

## 6. Plesk: subfolder and Git deployment

Do this once per game.

### 6a. Create the subfolder

1. Plesk -> **Websites & Domains** -> the site domain -> **File Manager**
   (or **Files** in the left menu).
2. Open the domain's root folder and create a new folder named after the game
   (e.g. `asteroidDoge`). The game will be served at
   `https://<domain>/<folder>/`.

### 6b. Connect Git

1. **Websites & Domains** -> expand the domain -> **Get Started** tab ->
   **Deploy using Git** (or the Git item under the domain's tools).
2. Add a repository: **Remote repository**.
   - **Repository URL:** `https://github.com/hingohongo-ai/<repo>.git`
   - **Repository name:** any unique label (the `.git` suffix it pre-fills is fine)
   - **Deployment mode:** Automatic
   - **Deployment directory:** pick the game's subfolder with the folder picker.
     (For the front-page repo `baseCamp`, use the domain's root folder instead.)
   - Leave "post deployment actions" off.
3. Click **Create**.
4. There is no branch field in the create form. Open the new repository's page
   and change its branch from the default to **`deploy`**, then run a
   pull/deploy. If you skip this, Plesk deploys the raw source from `master`,
   which will not work as a site.
5. Note the repository's **webhook URL** shown on its page. Treat it as private.

## 7. GitHub webhook (deploy on every push)

1. Repo -> **Settings** tab (needs admin rights; sign in as `hingohongo-ai`)
   -> **Webhooks** (left sidebar, "Code and automation") -> **Add webhook**.
   Direct link: https://github.com/hingohongo-ai/<repo>/settings/hooks/new
2. **Payload URL:** the Plesk webhook URL. **Content type:** `application/json`.
   **Secret:** blank. **SSL verification:** enabled.
   **Events:** "Just the push event". **Active:** checked.
3. Save. Open the webhook's **Recent Deliveries**: a green check means Plesk answered.
4. Each push to `master` produces two deliveries: one immediately (Plesk finds
   nothing new on `deploy`) and one when the Action publishes `deploy` (the real deploy).

## 8. Verify end to end

1. Open `https://<domain>/<game folder>/`.
2. Make a small visible change, commit, and `git push`.
3. Wait for the green Action run, then about a minute, and reload.

## 9. Add the game to the Base Camp front page

1. In the `baseCamp` repo edit `site/index.html` to add a link or card to `/<game folder>/`.
2. Commit and push to `master`. The front page redeploys itself.

Caution: `baseCamp` deploys into the domain root, which also holds the game
subfolders. If a front-page deploy ever removes a game folder, redeploy that
game's repo. Before changing the root deployment, download a backup of the
root folder from File Manager.

## 10. Troubleshooting

| Symptom | Likely cause and fix |
|---|---|
| "Password authentication is not supported" | You entered your account password. Use the token (section 2) and clear Credential Manager entries. |
| 403 "Permission denied to <user>" | Another GitHub account is cached. Remove `git:https://github.com` entries in Credential Manager. |
| Push rejected mentioning workflow scope | Token lacks **Workflows: Read and write**. |
| Push to a new repo fails authentication | Token does not include that repo. Add it (section 2). |
| No Settings tab on the repo | Signed in as the wrong GitHub account. |
| Can't find Developer settings | Use account Settings (profile picture), bottom of the left sidebar, or the direct link in section 2. |
| Site shows raw source or a blank page | Plesk is on the wrong branch. Switch to `deploy` and pull. |
| Page loads but scripts/CSS 404 | `base: './'` missing from `vite.config.js`. |
| Site does not update after a push | Check the Action is green, webhook **Recent Deliveries**, then try a manual pull in Plesk. |
| Plesk says document root is not within the subscription | The new domain's path must be inside the subscription's webspace (relative folder such as `/testsite`). |
| `npm` or `node` not found | Install Node LTS and reopen the terminal. |
| Domain lookup says "Query refused" from `ns.xmission.com` | DNS zone not created or not propagated yet (section 11c). |
| Let's Encrypt "secondary validation" DNS error | Zone not on all name servers yet. Wait and retry (section 11d). |
| Padlock on `www` but "Not secure" on the bare domain | Usually stale browser state. Test in a private window; clear site data (section 11e). |
| Deploys still go to the old folder after renaming | Update each Plesk Git repo's deployment directory (section 11b). |

## 11. Add a domain: DNS and HTTPS

Do this when registering a new domain for the site, or swapping the domain.
Registrar screens vary, so only the settings are listed.

### 11a. Register and point at xmission

1. Register the `.com` at any registrar. At the registrar, set the domain's
   **name servers** to xmission's: `ns.xmission.com` and `ns1.xmission.com`
   (and any others xmission lists in Plesk's DNS settings).
2. Registration can take hours to show up. A brand-new name may fail DNS lookups
   for a while even though it is registered. Registry lookups (RDAP) are more
   reliable than DNS for "is it taken?".

### 11b. Add the domain in Plesk

1. **Websites & Domains** -> **Add Domain** (or change the existing domain name).
2. Hosting type **Website**. Document root inside the subscription's webspace
   (for example `/skeletonbelly.com`). A path outside the webspace gives
   "not located within the subscription's root directory".
3. Renaming the storage folder is fine, but then **update every Plesk Git
   repository's deployment directory** (section 6b) to the new path, and
   re-test with a push. Otherwise deploys keep writing to the old folder.

### 11c. Create the DNS zone

1. Open the domain -> **DNS Settings** and make sure a zone exists.
2. Records needed:
   - `A`: name `skeletonbelly.com.` -> `166.70.198.108`
   - `CNAME`: `www` -> `skeletonbelly.com.`
3. If a lookup against xmission's servers answers **Query refused**, the zone
   has not been created or has not reached all name servers yet. Check with:
   ```
   nslookup skeletonbelly.com ns.xmission.com
   ```
   Wait and retry. Do not request a certificate until this returns the A record.
4. A banner in Plesk saying the name "does not resolve" is expected until the
   zone propagates (up to 24 hours, usually much sooner).

### 11d. Get the HTTPS certificate

1. Domain -> **SSL/TLS Certificates** -> **Get it free** (Let's Encrypt).
   Tick the options for `www` as well as the bare domain.
2. If it fails with "During secondary validation: DNS problem: networking error
   looking up A/AAAA": Let's Encrypt checks from several places and some
   xmission name servers do not have the zone yet. Wait 15 to 60 minutes and retry.
3. Domain -> **Hosting Settings**: pick the new certificate in the **Certificate**
   dropdown. Turn on **Permanent SEO-safe 301 redirect from HTTP to HTTPS**
   so anyone typing the bare name lands on `https://`. Save.
4. Check both `https://skeletonbelly.com` and `https://www.skeletonbelly.com`.

### 11e. If the browser still says "Not secure"

When another browser or a private window shows the padlock, the server is fine
and the regular browser is holding stale state for that site. Clear that
site's data (Brave/Chrome: lock or site-info icon -> Site settings -> Delete
data), or restart the browser. Also try a phone on cellular data. If the
certificate details name antivirus software instead of Let's Encrypt, turn off
its HTTPS scanning.
