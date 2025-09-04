// Helper functions
function isValidEmail(email) {
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return emailRegex.test(email);
}

function showFormError(element, message) {
  element.addClass("input-error");
  element.after('<div class="error-message">' + escapeHtml(message) + "</div>");
}

function showSuccessMessage(container, message) {
  const existingMessage = container.find(".success-message");
  if (existingMessage.length) {
    existingMessage.text(message);
  } else {
    container
      .find(".fact-check-box")
      .prepend(
        '<div class="success-message">' + escapeHtml(message) + "</div>"
      );
  }

  setTimeout(function () {
    container.find(".success-message").fadeOut(function () {
      $(this).remove();
    });
  }, 3000);
}

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

  let issuesHtml = "";
  if (data.issues && data.issues.length > 0) {
    issuesHtml = `
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
                            <div class="issue-type">${escapeHtml(
                              issue.type
                            )}</div>
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
            `;
  }

  let sourcesHtml = "";
  if (data.sources && data.sources.length > 0) {
    // Filter out any invalid URLs
    const validSources = data.sources.filter(
      (source) =>
        source &&
        source.url &&
        source.title &&
        (source.url.startsWith("http://") || source.url.startsWith("https://"))
    );

    if (validSources.length > 0) {
      sourcesHtml = `
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
                `;
    }
  }

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
                    <div class="progress-bar">
                        <div class="progress-fill"></div>
                    </div>
                    <button class="voice-control" title="Download Report" onclick="downloadReport()">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <polyline points="7,10 12,15 17,10"></polyline>
                            <line x1="12" y1="15" x2="12" y2="3"></line>
                        </svg>
                    </button>
                    <button class="voice-control" title="Share Results" onclick="shareResults()">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="18" cy="5" r="3"></circle>
                            <circle cx="6" cy="12" r="3"></circle>
                            <circle cx="18" cy="19" r="3"></circle>
                            <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line>
                            <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line>
                        </svg>
                    </button>
                </div>
            </div>
            
            <div class="fact-check-timestamp">
                Last verified: ${timeString}
            </div>
        `;

  container.html(html);
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
                    <button class="voice-control" title="Retry" onclick="factCheckerStart()">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="23,4 23,10 17,10"></polyline>
                            <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
                        </svg>
                    </button>
                </div>
            </div>
        `;

  container.html(html).show();
}

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

jQuery(document).ready(function ($) {
  "use strict";

  // Cache for storing results per post ID to handle autoload
  let factCheckCache = {};
  let activeRequests = {};

  // Initialize the fact checker (called on page load and autoload)
  function initFactChecker() {
    // Set CSS custom properties for colors and apply theme
    if (typeof factChecker !== "undefined" && factChecker.colors) {
      const root = document.documentElement;
      root.style.setProperty("--fc-primary", factChecker.colors.primary);
      root.style.setProperty("--fc-success", factChecker.colors.success);
      root.style.setProperty("--fc-warning", factChecker.colors.warning);
      root.style.setProperty("--fc-background", factChecker.colors.background);

      // Apply theme mode to all fact check containers
      $(".fact-check-container").removeClass("theme-dark theme-light");
      if (factChecker.theme_mode === "dark") {
        $(".fact-check-container").addClass("theme-dark");
      } else {
        $(".fact-check-container").addClass("theme-light");
      }
    }

    // Clear any existing results that might be showing incorrect data
    $(".fact-check-container").each(function () {
      const container = $(this);
      const postId = container.data("post-id");
      const resultsContainer = container.find("#fact-check-results");
      const button = container.find(".check-button");

      // Reset button state
      button.removeClass("loading");
      button.html(
        '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"></path></svg><span>Check Facts</span>'
      );

      // Clear results unless they belong to current post
      if (postId && factCheckCache[postId]) {
        displayResults(factCheckCache[postId], resultsContainer);
        resultsContainer.show();
      } else {
        resultsContainer.hide().empty();
      }
    });
  }

  // Initialize on page load
  initFactChecker();

  // Re-initialize when new content is loaded (autoload compatibility)
  $(document).on("DOMNodeInserted", function (e) {
    if (
      $(e.target).find(".fact-check-container").length > 0 ||
      $(e.target).hasClass("fact-check-container")
    ) {
      setTimeout(initFactChecker, 100);
    }
  });

  // Listen for common autoload events
  $(document).on("autoload:complete page:change content:loaded", function () {
    setTimeout(initFactChecker, 100);
  });

  // Global fact checker function
  window.factCheckerStart = function (element) {
    // Find the closest fact-check-container
    const container = element
      ? $(element).closest(".fact-check-container")
      : $(".fact-check-container").first();
    const button = container.find(".check-button");
    const resultsContainer = container.find("#fact-check-results");
    const postId = container.data("post-id");

    if (!postId) {
      showError(
        "Unable to identify article. Please refresh the page.",
        resultsContainer
      );
      return;
    }

    if (button.hasClass("loading") || activeRequests[postId]) {
      return;
    }

    // Check if we have cached results for this specific post
    if (factCheckCache[postId]) {
      displayResults(factCheckCache[postId], resultsContainer);
      resultsContainer.show();
      return;
    }

    // Show loading state
    button.addClass("loading");
    button.html(
      '<div class="loading-spinner"></div><span>Analyzing & Searching...</span>'
    );
    resultsContainer.hide();

    // Cancel any existing request for this post
    if (activeRequests[postId]) {
      activeRequests[postId].abort();
    }

    // Make AJAX request with extended timeout for web search
    activeRequests[postId] = $.ajax({
      url: factChecker.ajaxUrl,
      type: "POST",
      data: {
        action: "fact_check_article",
        post_id: postId,
        nonce: factChecker.nonce,
      },
      timeout: 180000, // 3 minutes timeout for comprehensive web search
      success: function (response) {
        if (response.success) {
          // Cache the results for this specific post
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
      error: function (xhr, status, error) {
        if (status === "abort") {
          return; // Request was cancelled
        }

        let errorMessage = "Analysis failed. Please try again.";
        if (status === "timeout") {
          errorMessage =
            "Analysis timed out. The web search may have taken too long. Please try again.";
        } else if (xhr.responseJSON && xhr.responseJSON.data) {
          errorMessage = xhr.responseJSON.data;
        }
        showError(errorMessage, resultsContainer);
      },
      complete: function () {
        // Reset button state
        button.removeClass("loading");
        button.html(
          '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg><span>Re-check</span>'
        );

        // Clear active request
        delete activeRequests[postId];
      },
    });
  };

  // Update the HTML template to pass the container element
  $(document).on("click", ".check-button", function (e) {
    e.preventDefault();
    factCheckerStart(this);
  });

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

    let issuesHtml = "";
    if (data.issues && data.issues.length > 0) {
      issuesHtml = `
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
                            <div class="issue-type">${escapeHtml(
                              issue.type
                            )}</div>
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
            `;
    }

    let sourcesHtml = "";
    if (data.sources && data.sources.length > 0) {
      // Filter out any invalid URLs
      const validSources = data.sources.filter(
        (source) =>
          source &&
          source.url &&
          source.title &&
          (source.url.startsWith("http://") ||
            source.url.startsWith("https://"))
      );

      if (validSources.length > 0) {
        sourcesHtml = `
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
                `;
      }
    }

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
                    <div class="progress-bar">
                        <div class="progress-fill"></div>
                    </div>
                    <button class="voice-control" title="Download Report" onclick="downloadReport()">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <polyline points="7,10 12,15 17,10"></polyline>
                            <line x1="12" y1="15" x2="12" y2="3"></line>
                        </svg>
                    </button>
                    <button class="voice-control" title="Share Results" onclick="shareResults()">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="18" cy="5" r="3"></circle>
                            <circle cx="6" cy="12" r="3"></circle>
                            <circle cx="18" cy="19" r="3"></circle>
                            <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line>
                            <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line>
                        </svg>
                    </button>
                </div>
            </div>
            
            <div class="fact-check-timestamp">
                Last verified: ${timeString}
            </div>
        `;

    container.html(html);
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
                    <button class="voice-control" title="Retry" onclick="factCheckerStart()">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="23,4 23,10 17,10"></polyline>
                            <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
                        </svg>
                    </button>
                </div>
            </div>
        `;

    container.html(html).show();
  }

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

  // Download report functionality
  window.downloadReport = function () {
    const container = $(".fact-check-container");
    const postTitle = $("h1").first().text() || document.title;
    const results = container.find("#fact-check-results");

    if (!results.is(":visible")) {
      alert("No report to download. Please run a fact check first.");
      return;
    }

    const scoreNumber = results.find(".score-number").text();
    const scoreTitle = results
      .find(".score-title")
      .text()
      .replace(/[✓⚠✗]/g, "")
      .trim();
    const scoreDescription = results.find(".score-subtitle").text();

    let reportContent = `FACT CHECK REPORT\n`;
    reportContent += `===================\n\n`;
    reportContent += `Article: ${postTitle}\n`;
    reportContent += `URL: ${window.location.href}\n`;
    reportContent += `Date: ${new Date().toLocaleDateString()}\n`;
    reportContent += `Time: ${new Date().toLocaleTimeString()}\n\n`;
    reportContent += `ANALYSIS RESULTS:\n`;
    reportContent += `-----------------\n`;
    reportContent += `Score: ${scoreNumber}/100\n`;
    reportContent += `Status: ${scoreTitle}\n`;
    reportContent += `Description: ${scoreDescription}\n\n`;

    const issues = results.find(".issue-item");
    if (issues.length > 0) {
      reportContent += `ISSUES IDENTIFIED:\n`;
      reportContent += `==================\n\n`;

      issues.each(function (index) {
        const type = $(this).find(".issue-type").text();
        const description = $(this).find(".issue-description").text();
        const suggestion = $(this).find(".issue-suggestion").text();

        reportContent += `${index + 1}. ${type}\n`;
        reportContent += `   Problem: ${description}\n`;
        reportContent += `   ${suggestion}\n\n`;
      });
    }

    const sources = results.find(".source-link");
    if (sources.length > 0) {
      reportContent += `WEB SOURCES VERIFIED:\n`;
      reportContent += `=====================\n\n`;

      sources.each(function (index) {
        reportContent += `${index + 1}. ${$(this).text()}\n`;
        reportContent += `   URL: ${$(this).attr("href")}\n\n`;
      });
    }

    reportContent += `\n`;
    reportContent += `METHODOLOGY:\n`;
    reportContent += `============\n`;
    reportContent += `This fact-check was performed using AI analysis combined with real-time web search.\n`;
    reportContent += `The system searched current web sources to verify factual claims in the article.\n`;
    reportContent += `All sources listed above were accessed during the verification process.\n\n`;

    reportContent += `Generated by Fact Checker Plugin\n`;
    reportContent += `Powered by OpenRouter Web Search\n`;
    reportContent += `Plugin by Mohamed Sawah - https://sawahsolutions.com`;

    // Create and download file
    const blob = new Blob([reportContent], { type: "text/plain" });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `fact-check-report-${slugify(
      postTitle
    )}-${new Date().getTime()}.txt`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
  };

  // Share results functionality
  window.shareResults = function () {
    const container = $(".fact-check-container");
    const postTitle = $("h1").first().text() || document.title;
    const results = container.find("#fact-check-results");

    if (!results.is(":visible")) {
      alert("No results to share. Please run a fact check first.");
      return;
    }

    const scoreNumber = results.find(".score-number").text();
    const scoreTitle = results
      .find(".score-title")
      .text()
      .replace(/[✓⚠✗]/g, "")
      .trim();

    const shareText = `Fact Check Results for "${postTitle}"\n\nScore: ${scoreNumber}/100 - ${scoreTitle}\n\nVerified using AI with real-time web search.\n\n${window.location.href}`;

    if (navigator.share) {
      // Use native sharing if available
      navigator
        .share({
          title: `Fact Check: ${postTitle}`,
          text: shareText,
          url: window.location.href,
        })
        .catch((err) => console.log("Error sharing:", err));
    } else {
      // Fallback: copy to clipboard
      if (navigator.clipboard) {
        navigator.clipboard.writeText(shareText).then(
          function () {
            alert("Fact check results copied to clipboard!");
          },
          function () {
            fallbackCopyTextToClipboard(shareText);
          }
        );
      } else {
        fallbackCopyTextToClipboard(shareText);
      }
    }
  };

  function fallbackCopyTextToClipboard(text) {
    const textArea = document.createElement("textarea");
    textArea.value = text;

    // Avoid scrolling to bottom
    textArea.style.top = "0";
    textArea.style.left = "0";
    textArea.style.position = "fixed";

    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();

    try {
      const successful = document.execCommand("copy");
      if (successful) {
        alert("Fact check results copied to clipboard!");
      } else {
        alert("Could not copy results. Please select and copy manually.");
      }
    } catch (err) {
      alert("Could not copy results. Please select and copy manually.");
    }

    document.body.removeChild(textArea);
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

  // Progress bar animation (cosmetic)
  function animateProgressBar() {
    const progressBar = $(".progress-fill");
    if (progressBar.length) {
      let width = 0;
      const interval = setInterval(function () {
        width += Math.random() * 10;
        if (width >= 100) {
          width = 100;
          clearInterval(interval);
        }
        progressBar.css("width", width + "%");
      }, 200);
    }
  }

  // Initialize progress bar animation when results are shown
  $(document).on("DOMNodeInserted", ".results-container", function () {
    setTimeout(animateProgressBar, 500);
  });

  // Keyboard accessibility
  $(document).on("keydown", ".fact-check-container", function (e) {
    if (e.key === "Enter" || e.key === " ") {
      if (e.target.classList.contains("check-button")) {
        e.preventDefault();
        factCheckerStart();
      }
    }
  });

  // Auto-retry mechanism for failed requests
  let retryCount = 0;
  const maxRetries = 2;

  function autoRetryFactCheck() {
    if (retryCount < maxRetries) {
      retryCount++;
      setTimeout(function () {
        console.log("Auto-retrying fact check, attempt:", retryCount);
        factCheckerStart();
      }, 3000);
    }
  }

  // Enhanced error handling with auto-retry
  $(document).ajaxError(function (event, xhr, settings) {
    if (settings.data && settings.data.includes("fact_check_article")) {
      if (xhr.status === 0 || xhr.status >= 500) {
        // Network error or server error - try again
        autoRetryFactCheck();
      }
    }
  });

  // Clean up on page unload
  $(window).on("beforeunload", function () {
    // Cancel any ongoing requests
    if (window.activeFactCheckRequest) {
      window.activeFactCheckRequest.abort();
    }
  });

  // Store active request for cleanup
  const originalAjax = $.ajax;
  $.ajax = function (options) {
    const request = originalAjax.call(this, options);
    if (
      options.data &&
      options.data.includes &&
      options.data.includes("fact_check_article")
    ) {
      window.activeFactCheckRequest = request;
      request.always(function () {
        window.activeFactCheckRequest = null;
      });
    }
    return request;
  };
});
