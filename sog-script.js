/*!
 * Plugin Name: Secure Outbound Gateway (SOG)
 * Description: JS logic for external link and exceptions.
 * Author: Agustin S
 * Version: 1.0
 */

document.addEventListener("DOMContentLoaded", function () {
    const currentHost = window.location.host;

    fetch(`${sog_ajax.plugin_url}exceptions.json`)
        .then(response => {
            if (!response.ok) throw new Error("Failed to load exceptions.json");
            return response.json();
        })
        .then(exceptions => {
            initModal(exceptions);
        })
        .catch(error => {
            console.error("Error loading exceptions list:", error);
            initModal([]); // continuar sin excepciones
        });

    function initModal(exceptions) {
        const modal = document.createElement("div");
        modal.innerHTML = `
          <div class="sog-modal-overlay" id="sogModal">
            <div class="sog-modal-box">
              <h2>
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                  <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10
                           10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                </svg>
                Warning notice
              </h2>
              <p>
                You are leaving <strong>root-view.com</strong> to access an external site.
              </p>
              <p>
                <strong>Root View</strong> is not responsible for the content, accuracy, availability or security policies of the site that will be redirected. Access is achieved without exclusive liability.
              </p>
              <p id="sog-link-display" class="sog-url"></p>
              <div class="sog-buttons">
                <button id="sog-cancel" aria-label="Cancel and stay on root-view.com">Cancel</button>
                <button id="sog-continue" aria-label="Continue and visit the external site">Continue</button>
              </div>
            </div>
          </div>
        `;
        document.body.appendChild(modal);

        const sogModal = document.getElementById("sogModal");
        const sogLinkDisplay = document.getElementById("sog-link-display");
        const sogCancel = document.getElementById("sog-cancel");
        const sogContinue = document.getElementById("sog-continue");

        let targetUrl = null;

        function logClick(url) {
            fetch(sog_ajax.ajax_url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=sog_log_click&url=${encodeURIComponent(url)}`
            });
        }

        document.querySelectorAll("a[href^='http']").forEach(link => {
            const url = new URL(link.href);
            if (url.host !== currentHost) {
                link.addEventListener("click", function (e) {
                    e.preventDefault();
                    logClick(link.href);

                    const isException = exceptions.some(exc =>
                        exc.startsWith("http") ? link.href.startsWith(exc) : url.host === exc
                    );

                    if (isException) {
                        window.location.href = link.href;
                        return;
                    }

                    targetUrl = link.href;
                    sogLinkDisplay.textContent = targetUrl;
                    sogModal.style.display = "flex";
                });
            }
        });

        sogCancel.addEventListener("click", () => {
            sogModal.style.display = "none";
            targetUrl = null;
        });

        sogContinue.addEventListener("click", () => {
            if (targetUrl) {
                window.location.href = targetUrl;
            }
        });
    }
});
