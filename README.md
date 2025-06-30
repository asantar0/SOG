# SOG – Secure Outbound Gateway for WordPress

**SOG** (Secure Outbound Gateway) is a WordPress plugin that displays a customizable warning when visitors click on external links. It is designed for websites that prioritize security, transparency, and control over user behavior when leaving the site.


## Key Features

- Legal warning modal before leaving the site.
- Customizable text with a professional design.
- Automatic logging of external link clicks.
- Whitelist for trusted domains or internal subdomains.
- Visual style adaptable via CSS.
- Use of ipinfo.io to log Countries.
- Localstore in order to avoid warning message to same site accepted.


## Installation

1. Clone or download the repository:

   ```bash
   git clone https://github.com/asantar0/sog.git
   ```

2. Upload the `sog` folder to your `/wp-content/plugins/` directory.

3. Activate the plugin from the WordPress admin dashboard.


## Roadmap

- Admin panel with click statistics
- Multi-language support (WPML, Polylang, browser detection)
- Advanced exceptions (by pattern, link type, or category)
- Log export (CSV)
- Integration with Google Analytics or Matomo
- Email/Slack alerts for critical clicks
- Delete accepted domains button
- Tracking with Matomo/Google Analytics/Microsoft Clarity
- Web admin


## License

This plugin is released under the [MIT License](./LICENSE).


## Contact

For feature requests, improvements, or bug reports, please open an issue in this repository.

agustins@root-view.com
