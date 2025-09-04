jQuery(document).ready(function ($) {
  "use strict";

  // Set CSS custom properties for colors
  if (typeof factChecker !== "undefined" && factChecker.colors) {
    const root = document.documentElement;
    root.style.setProperty("--fc-primary", factChecker.colors.primary);
    root.style.setProperty("--fc-success", factChecker.colors.success);
    root.style.setProperty("--fc-warning", factChecker.colors.warning);
    root.style.setProperty("--fc-background", factChecker.colors.background);
  }

  window.factCheckerStart = function () {
    const container = $(".fact-check-container");
    const button = container.find(".check-button");
    const resultsContainer = container.find("#fact-check-results");
    const postId = container.data("post-id");

    if (button.hasClass("loading")) {
      return;
    }

    // Show loading state
    button.addClass("loading");
    button.html('<div class="loading-spinner"></div><span>Analyzing...</span>');
    resultsContainer.hide();

    // Make AJAX request
    $.ajax({
      url: factChecker.ajaxUrl,
      type: "POST",
      data: {
        action: "fact_check_article",
        post_id: postId,
        nonce: factChecker.nonce,
      },
      timeout: 60000, // 60 seconds timeout
      success: function (response) {
        if (response.success) {
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
        let errorMessage = "Analysis failed. Please try again.";
        if (status === "timeout") {
          errorMessage = "Analysis timed out. Please try again.";
        } else if (xhr.responseJSON && xhr.responseJSON.data) {
          errorMessage = xhr.responseJSON.data;
        }
        showError(errorMessage, resultsContainer);
      },
      complete: function () {
        // Reset button
        button.removeClass("loading");
        button.html(
          '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg><span>Re-check</span>'
        );
      },
    });
  };

  function displayResults(data, container) {
    const now = new Date();
    const timeString =
      now.toLocaleDateString() +
      " • " +
      now.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" });

    let statusClass = "status-good";
    let statusText = "✓ Verified";

    if (data.score < 50) {
      statusClass = "status-error";
      statusText = "⚠ Issues Found";
    } else if (data.score < 80) {
      statusClass = "status-warning";
      statusText = "⚠ Needs Review";
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
      sourcesHtml = `
                <div class="sources-section">
                    <h4 class="sources-title">Sources Checked</h4>
                    ${data.sources
                      .map(
                        (source) => `
                        <a href="${escapeHtml(
                          source.url
                        )}" class="source-link" target="_blank" rel="noopener noreferrer">
                            ${escapeHtml(source.title)}
                        </a>
                    `
                      )
                      .join("")}
                </div>
            `;
    }

    const html = `
            <div class="score-section">
                <div class="score-display">
                    <div class="score-number">${data.score}</div>
                    <div class="score-label">Score</div>
                </div>
                <div class="score-description">
                    <div class="score-title">
                        ${escapeHtml(data.status || "Analysis Complete")}
                        <span class="status-indicator ${statusClass}">${statusText}</span>
                    </div>
                    <div class="score-subtitle">${escapeHtml(
                      data.description || "Fact-checking analysis completed."
                    )}</div>
                </div>
            </div>
            
            ${issuesHtml}
            ${sourcesHtml}
            
            <div class="voicing-info">
                <span>Powered by AI • ${
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
                    <button class="voice-control" title="Settings">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="3"></circle>
                            <path d="m12 1 1.68 2.34L16.5 3l.5 2.84L19.84 6l-.5 2.84L16.5 21l-2.82-.34L12 23l-1.68-2.34L7.5 21l-.5-2.84L4.16 18l.5-2.84L7.5 3l2.82.34L12 1z"></path>
                        </svg>
                    </button>
                </div>
            </div>
            
            <div class="timestamp">
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
    reportContent += `Date: ${new Date().toLocaleDateString()}\n`;
    reportContent += `Score: ${scoreNumber}/100\n`;
    reportContent += `Status: ${scoreTitle}\n`;
    reportContent += `Description: ${scoreDescription}\n\n`;

    const issues = results.find(".issue-item");
    if (issues.length > 0) {
      reportContent += `ISSUES FOUND:\n`;
      reportContent += `=============\n\n`;

      issues.each(function (index) {
        const type = $(this).find(".issue-type").text();
        const description = $(this).find(".issue-description").text();
        const suggestion = $(this).find(".issue-suggestion").text();

        reportContent += `${index + 1}. ${type}\n`;
        reportContent += `   ${description}\n`;
        reportContent += `   ${suggestion}\n\n`;
      });
    }

    const sources = results.find(".source-link");
    if (sources.length > 0) {
      reportContent += `SOURCES VERIFIED:\n`;
      reportContent += `=================\n\n`;

      sources.each(function (index) {
        reportContent += `${index + 1}. ${$(this).text()}\n`;
        reportContent += `   ${$(this).attr("href")}\n\n`;
      });
    }

    reportContent += `Generated by Fact Checker Plugin\n`;
    reportContent += `https://sawahsolutions.com`;

    // Create and download file
    const blob = new Blob([reportContent], { type: "text/plain" });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `fact-check-report-${new Date().getTime()}.txt`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
  };
});
