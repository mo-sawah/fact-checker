jQuery(document).ready(function ($) {
  "use strict";

  // Cache for storing results per post ID to handle multiple instances and autoload.
  const factCheckCache = {};
  const activeRequests = {};
  let retryCount = 0;
  const maxRetries = 2;

  // Helper Functions
  // =============================================

  function escapeHtml(text) {
    if (typeof text !== "string") return "";
    const map = {
      "&": "&amp;",
      "<": "&lt;",
      ">": "&gt;",
      '"': "&quot;",
      "'": "&#039;",
    };
    return text.replace(/[&<>"']/g, function (m) {
      return map[m];
    });
  }

  function truncateTitle(title, maxLength) {
    if (title.length <= maxLength) return title;
    return title.substring(0, maxLength - 3) + "...";
  }

  function slugify(text) {
    return text
      .toString()
      .toLowerCase()
      .replace(/\s+/g, "-") // Replace spaces with -
      .replace(/[^\w\-]+/g, "") // Remove all non-word chars
      .replace(/\-\-+/g, "-") // Replace multiple - with single -
      .replace(/^-+/, "") // Trim - from start of text
      .replace(/-+$/, "") // Trim - from end of text
      .substring(0, 50); // Limit length
  }

  // UI and Display Functions
  // =============================================

  function displayResults(data, container) {
    const now = new Date();
    const timeString =
      now.toLocaleDateString("en-US", {
        month: "numeric",
        day: "numeric",
        year: "numeric",
      }) +
      " • " +
      now.toLocaleTimeString("en-US", {
        hour: "numeric",
        minute: "2-digit",
        hour12: true,
      });

    let statusClass = "status-good";
    let statusText = "✓ Verified";
    let scoreColor = "var(--fc-success, #059669)";

    if (data.score < 50) {
      statusClass = "status-error";
      statusText = "⚠ Issues Found";
      scoreColor = "#dc2626";
    } else if (data.score < 80) {
      statusClass = "status-warning";
      statusText = "⚠ Needs Review";
      scoreColor = "#d97706";
    }

    const issuesHtml =
      data.issues && data.issues.length > 0
        ? `
            <div class="issues-section">
                <h4 class="issues-title">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="#f59e0b" stroke="#f59e0b" stroke-width="2">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                        <line x1="12" y1="9" x2="12" y2="13"></line>
                        <line x1="12" y1="17" x2="12.01" y2="17"></line>
                    </svg>
                    Issues Found (${data.issues.length})
                </h4>
                ${data.issues
                  .map(
                    (issue) => `
                    <div class="issue-item">
                        <div class="issue-type">${escapeHtml(issue.type)}</div>
                        <div class="issue-description">${escapeHtml(
                          issue.description
                        )}</div>
                        <div class="issue-suggestion">
                            <strong>Suggested:</strong> ${escapeHtml(
                              issue.suggestion
                            )}
                        </div>
                    </div>
                `
                  )
                  .join("")}
            </div>
        `
        : "";

    const validSources = (data.sources || []).filter(
      (source) =>
        source &&
        source.url &&
        source.title &&
        (source.url.startsWith("http://") || source.url.startsWith("https://"))
    );

    const sourcesHtml =
      validSources.length > 0
        ? `
            <div class="sources-section">
                <h4 class="sources-title">Sources Verified</h4>
                ${validSources
                  .slice(0, 8)
                  .map(
                    (source) => `
                    <a href="${escapeHtml(
                      source.url
                    )}" class="source-link" target="_blank" rel="noopener noreferrer">
                        ${escapeHtml(truncateTitle(source.title, 80))}
                    </a>
                `
                  )
                  .join("")}
            </div>
        `
        : "";

    const html = `
            <div class="score-section">
                <div class="score-display">
                    <div class="score-number" style="color: ${scoreColor};">${
      data.score
    }</div>
                    <div class="score-label">Score</div>
                </div>
                <div class="score-description">
                    <div class="score-title">
                        ${escapeHtml(data.status || "Analysis Complete")}
                        <span class="status-indicator ${statusClass}">${statusText}</span>
                    </div>
                    <div class="score-subtitle">${escapeHtml(
                      data.description ||
                        "Web search and fact-checking analysis completed."
                    )}</div>
                </div>
            </div>
            ${issuesHtml}
            ${sourcesHtml}
            <div class="voicing-info">
                <span>Powered by AI with Web Search • ${
                  data.sources ? data.sources.length : 0
                } sources verified</span>
                <div class="voice-controls">
                    <span>0:00</span>
                    <div class="progress-bar"><div class="progress-fill"></div></div>
                    <button class="voice-control" title="Download Report" onclick="factChecker.downloadReport(this)">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7,10 12,15 17,10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                    </button>
                    <button class="voice-control" title="Share Results" onclick="factChecker.shareResults(this)">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="3"></circle><circle cx="6" cy="12" r="3"></circle><circle cx="18" cy="19" r="3"></circle><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line></svg>
                    </button>
                </div>
            </div>
            <div class="fact-check-timestamp">Last verified: ${timeString}</div>
        `;
    container.html(html);
    animateProgressBar(container);
  }

  function showError(message, container) {
    const html = `
            <div class="score-section" style="border-color: #f87171;">
                <div class="score-display">
                    <div class="score-number" style="color: #dc2626;">--</div>
                    <div class="score-label">Error</div>
                </div>
                <div class="score-description">
                    <div class="score-title">
                        Analysis Failed
                        <span class="status-indicator status-error">✗ Error</span>
                    </div>
                    <div class="score-subtitle">${escapeHtml(message)}</div>
                </div>
            </div>
            <div class="voicing-info">
                <span>Web search and analysis could not be completed</span>
                <div class="voice-controls">
                    <button class="voice-control" title="Retry" onclick="factChecker.start(this)">
                         <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23,4 23,10 17,10"></polyline><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path></svg>
                    </button>
                </div>
            </div>
        `;
    container.html(html).show();
  }

  function animateProgressBar(container) {
    const progressBar = container.find(".progress-fill");
    if (!progressBar.length) return;
    let width = 0;
    const interval = setInterval(() => {
      width += Math.random() * 10;
      if (width >= 100) {
        width = 100;
        clearInterval(interval);
      }
      progressBar.css("width", `${width}%`);
    }, 200);
  }

  // Core Functionality
  // =============================================

  function initFactChecker() {
    if (typeof factCheckerConfig !== "undefined" && factCheckerConfig.colors) {
      const root = document.documentElement;
      root.style.setProperty("--fc-primary", factCheckerConfig.colors.primary);
      root.style.setProperty("--fc-success", factCheckerConfig.colors.success);
      root.style.setProperty("--fc-warning", factCheckerConfig.colors.warning);
      root.style.setProperty(
        "--fc-background",
        factCheckerConfig.colors.background
      );
    }

    $(".fact-check-container").each(function () {
      const container = $(this);
      const postId = container.data("post-id");
      const resultsContainer = container.find("#fact-check-results");
      const button = container.find(".check-button");

      container
        .removeClass("theme-dark theme-light")
        .addClass(
          factCheckerConfig.theme_mode === "dark" ? "theme-dark" : "theme-light"
        );
      button
        .removeClass("loading")
        .html(
          '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"></path></svg><span>Check Facts</span>'
        );

      if (postId && factCheckCache[postId]) {
        displayResults(factCheckCache[postId], resultsContainer);
        resultsContainer.show();
      } else {
        resultsContainer.hide().empty();
      }
    });
  }

  // Making functions globally accessible via a namespace
  window.factChecker = {
    start: function (element) {
      const container = $(element).closest(".fact-check-container");
      const button = container.find(".check-button");
      const resultsContainer = container.find("#fact-check-results");
      const postId = container.data("post-id");

      if (!postId) {
        showError(
          "Unable to identify the article. Please refresh the page.",
          resultsContainer
        );
        return;
      }
      if (button.hasClass("loading") || activeRequests[postId]) return;

      if (factCheckCache[postId]) {
        displayResults(factCheckCache[postId], resultsContainer);
        resultsContainer.show();
        return;
      }

      button
        .addClass("loading")
        .html(
          '<div class="loading-spinner"></div><span>Analyzing & Searching...</span>'
        );
      resultsContainer.hide();

      if (activeRequests[postId]) activeRequests[postId].abort();

      activeRequests[postId] = $.ajax({
        url: factCheckerConfig.ajaxUrl,
        type: "POST",
        data: {
          action: "fact_check_article",
          post_id: postId,
          nonce: factCheckerConfig.nonce,
        },
        timeout: 180000, // 3 minutes
        success: function (response) {
          if (response.success) {
            factCheckCache[postId] = response.data;
            displayResults(response.data, resultsContainer);
            resultsContainer.show();
          } else {
            showError(
              response.data || "Analysis failed. Please try again.",
              resultsContainer
            );
          }
        },
        error: function (xhr, status) {
          if (status === "abort") return;
          let errorMessage = "An unknown error occurred. Please try again.";
          if (status === "timeout") {
            errorMessage = "Analysis timed out. The web search took too long.";
          } else if (xhr.responseJSON && xhr.responseJSON.data) {
            errorMessage = xhr.responseJSON.data;
          }
          showError(errorMessage, resultsContainer);
        },
        complete: function () {
          button
            .removeClass("loading")
            .html(
              '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg><span>Re-check</span>'
            );
          delete activeRequests[postId];
        },
      });
    },

    downloadReport: function (element) {
      const container = $(element).closest(".fact-check-container");
      const results = container.find("#fact-check-results");
      const postTitle = $("h1").first().text() || document.title;

      if (!results.is(":visible")) {
        alert("No report to download. Please run a fact check first.");
        return;
      }

      const scoreNumber = results.find(".score-number").text();
      const scoreTitle = results
        .find(".score-title")
        .contents()
        .filter(function () {
          return this.nodeType === 3;
        })
        .text()
        .trim();
      const scoreDescription = results.find(".score-subtitle").text();

      let reportContent =
        `FACT CHECK REPORT\n===================\n\n` +
        `Article: ${postTitle}\nURL: ${
          window.location.href
        }\nDate: ${new Date().toLocaleDateString()}\n\n` +
        `ANALYSIS RESULTS:\n-----------------\nScore: ${scoreNumber}/100\nStatus: ${scoreTitle}\nDescription: ${scoreDescription}\n\n`;

      const issues = results.find(".issue-item");
      if (issues.length > 0) {
        reportContent += `ISSUES IDENTIFIED:\n==================\n\n`;
        issues.each(function (index) {
          reportContent += `${index + 1}. ${$(this)
            .find(".issue-type")
            .text()}\n   Problem: ${$(this)
            .find(".issue-description")
            .text()}\n   ${$(this).find(".issue-suggestion").text()}\n\n`;
        });
      }

      const sources = results.find(".source-link");
      if (sources.length > 0) {
        reportContent += `WEB SOURCES VERIFIED:\n=====================\n\n`;
        sources.each(function (index) {
          reportContent += `${index + 1}. ${$(this).text().trim()}\n   URL: ${$(
            this
          ).attr("href")}\n\n`;
        });
      }

      reportContent += `METHODOLOGY:\n============\nThis fact-check was performed using AI analysis combined with real-time web search.\n\nGenerated by Fact Checker Plugin`;

      const blob = new Blob([reportContent], { type: "text/plain" });
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = url;
      a.download = `fact-check-report-${slugify(postTitle)}.txt`;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      window.URL.revokeObjectURL(url);
    },

    shareResults: function (element) {
      const container = $(element).closest(".fact-check-container");
      const results = container.find("#fact-check-results");
      const postTitle = $("h1").first().text() || document.title;

      if (!results.is(":visible")) {
        alert("No results to share. Please run a fact check first.");
        return;
      }

      const scoreNumber = results.find(".score-number").text();
      const scoreTitle = results
        .find(".score-title")
        .contents()
        .filter(function () {
          return this.nodeType === 3;
        })
        .text()
        .trim();
      const shareText = `Fact Check for "${postTitle}": ${scoreNumber}/100 - ${scoreTitle}. See more at ${window.location.href}`;

      if (navigator.share) {
        navigator.share({
          title: `Fact Check: ${postTitle}`,
          text: shareText,
          url: window.location.href,
        });
      } else if (navigator.clipboard) {
        navigator.clipboard
          .writeText(shareText)
          .then(() => alert("Fact check results copied to clipboard!"));
      }
    },
  };

  // Event Listeners
  // =============================================

  // Main button click
  $(document).on("click", ".check-button", function (e) {
    e.preventDefault();
    window.factChecker.start(this);
  });

  // Keyboard accessibility for buttons
  $(document).on("keydown", ".check-button, .voice-control", function (e) {
    if (e.key === "Enter" || e.key === " ") {
      e.preventDefault();
      $(this).trigger("click");
    }
  });

  // Auto-retry for network/server errors
  $(document).ajaxError(function (event, xhr, settings) {
    if (settings.data && settings.data.includes("action=fact_check_article")) {
      if (xhr.status === 0 || xhr.status >= 500) {
        if (retryCount < maxRetries) {
          retryCount++;
          setTimeout(() => window.factChecker.start(), 3000 * retryCount); // Retry with increasing delay
        }
      }
    }
  });

  // Re-initialize for autoloading content (e.g., infinite scroll)
  const observer = new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
      if (mutation.addedNodes.length) {
        const hasFactChecker =
          $(mutation.addedNodes).find(".fact-check-container").length > 0 ||
          $(mutation.addedNodes).hasClass("fact-check-container");
        if (hasFactChecker) {
          setTimeout(initFactChecker, 100);
        }
      }
    });
  });
  observer.observe(document.body, { childList: true, subtree: true });

  // Clean up pending requests on page unload
  $(window).on("beforeunload", function () {
    // FIX: Correctly iterates through the activeRequests object to abort all pending requests.
    Object.values(activeRequests).forEach((request) => {
      if (request && typeof request.abort === "function") {
        request.abort();
      }
    });
  });

  // Initial load
  initFactChecker();
});
