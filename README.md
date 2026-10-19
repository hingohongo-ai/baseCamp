# Base Camp

The front door for the games site: a single static page (`site/index.html`) served at the
root of the domain. Each game lives in its own repo and its own subfolder
(for example `/asteroidDoge/`), and this page is where they get linked.

No build step and no dependencies. Edit the HTML, push, and the site updates.

## Layout

    site/index.html                 the page served at the domain root
    .github/workflows/deploy.yml    publishes site/ to the "deploy" branch
    README.md                       this file

## Edit

Open `site/index.html` in a browser to preview it (just double-click the file),
change it, then commit and push to `master`.

## Deploy (GitHub -> Plesk)

1. Push to `master`. The workflow in `.github/workflows/deploy.yml` copies only the contents
   of `site/` to a branch named `deploy` (force-pushed each time), so README and config files
   never reach the web root.
2. In Plesk (Websites & Domains -> Deploy using Git) the repository
   `https://github.com/hingohongo-ai/baseCamp.git` is tracked on the `deploy` branch, in
   Automatic mode, with the deployment directory set to the domain's root folder.
3. A GitHub webhook (repo Settings -> Webhooks) calls Plesk's webhook URL on every push, which
   triggers the deploy. Keep that URL private.

### First-time setup checklist

- Create the Plesk Git repository, then switch its branch from the default to `deploy`
  and pull.
- Add the Plesk webhook URL in GitHub.
- Make sure your GitHub personal access token has access to this repository
  (Contents and Workflows: read and write).

### Caution: shared web root

This site deploys into the domain root, which also contains the game subfolders
(such as `asteroidDoge/`). Those are deployed by their own repos and are not part of this one.
If a deploy ever removes a game folder, re-deploy that game's repo.

## Adding a game to the front page

1. Deploy the game from its own repo into a new subfolder of the domain.
2. Add a link or card for it in `site/index.html`, then push to `master`.
