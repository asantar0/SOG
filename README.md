# SOG – Secure Outbound Gateway for WordPress

**SOG** (Secure Outbound Gateway) is a WordPress plugin that displays a customizable warning when visitors click on external links. It is designed for websites that prioritize security, transparency, and control over user behavior when leaving the site.

## Why?
### Comply with regulations or good practices
- Regulations such as GDPR, HIPAA, and even internal guidelines from many public and private institutions recommend warning users before redirecting them off-domain.
- It can be part of a security or UX (user experience) audit.

### Reduce legal and reputational risks
If your site redirects to third-party services (links to banks, affiliates, etc.), notifying the user before leaving can protect you from claims if that third party has problems.

### Improve user experience and build trust
- When visitors see a warning like, "You are about to leave this site," they feel that you care about their security.
- This increases the perception of the website or company's seriousness.


## Key Features

- Legal warning modal before leaving the site.
- Customizable text with a professional design.
- Automatic logging of external link clicks.
- Whitelist for trusted domains or internal subdomains.
- Visual style adaptable via CSS.
- Use of ipinfo.io to log Countries.
- Localstore in order to avoid warning message to same site accepted.


## External Link Security (`rel="noopener noreferrer"`)

The plugin offers an optional feature to automatically add `rel="noopener noreferrer"` to all external links.

- noopener: Prevents the new tab from accessing window.opener, mitigating tabnabbing attacks.
- noreferrer: In addition to blocking window.opener, this prevents the Referer (source URL) from being sent to the destination site.

### Why it matters
- **Security**: Prevents reverse tabnabbing attacks.
- **Privacy**: Stops leaking the source site through HTTP `Referer`.

### How to enable
- Go to **Settings → SOG**
- Enable the option: _“Add `noopener noreferrer` to external links”_


## Installation

### From source

1. Clone or download the repository:

   ```bash
   git clone https://github.com/asantar0/sog.git
   ```

2. Upload the `sog` folder to your `/wp-content/plugins/` directory.

3. Activate the plugin from the WordPress admin dashboard.

### From release
Go to release section in this proyect.


## Admin panel

From the WordPress backend you can:
- Edit the whitelist of trusted domains.
- Automatically validate that the entered URLs are correctly formatted.
- Delete the audit log with a single click.
- Set IP Info token.
- Receive an automatic email notification each time the log is deleted. [PENDING]


## Roadmap

- Admin panel with click statistics
- Multi-language support (WPML, Polylang, browser detection)
- Advanced exceptions (by pattern, link type, or category)
- Log export (CSV)
- Integration with Google Analytics or Matomo
- Email/Slack alerts for critical clicks
- Delete accepted domains button
- Tracking with Matomo/Google Analytics/Microsoft Clarity


## License

This plugin is released under the [MIT License](./LICENSE).


## Contact

For feature requests, improvements, or bug reports, please open an issue in this repository.

agustins@root-view.com

## Donations

If you find this plugin useful, you can buy me a coffee:

[![Buy Me a Coffee](https://img.shields.io/badge/Coffee%20for%20me-%E2%98%95-lightgrey?logo=buy-me-a-coffee)](https://coff.ee/agustins)

