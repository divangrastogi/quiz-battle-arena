/**
 * Public JavaScript for Quiz Battle Arena
 *
 * Handles public-facing interactions and initializes battle functionality
 *
 * @package QuizBattleArena
 * @since 1.0.0
 */

(function ($) {
  "use strict";

  /**
   * QBA Public Handler
   */
  window.QBA_Public = {
    /**
     * Initialize public functionality
     */
    init: function () {
      this.bindEvents();
      this.initializeBattleButtons();
    },

    /**
     * Bind event handlers
     */
    bindEvents: function () {
      var self = this;

      // Battle buttons on quiz pages
      $(document).on("click", ".qba-quick-battle-btn", function (e) {
        e.preventDefault();
        var quizId = $(this).data("quiz-id");
        self.startBattle(quizId, "quick");
      });

      $(document).on("click", ".qba-challenge-friend-btn", function (e) {
        e.preventDefault();
        var quizId = $(this).data("quiz-id");
        self.startBattle(quizId, "challenge");
      });

      // Leaderboard interactions
      $(document).on("click", ".qba-view-full-leaderboard", function (e) {
        e.preventDefault();
        self.showLeaderboardModal();
      });

      $(document).on("change", ".qba-leaderboard-filters select", function () {
        self.loadLeaderboardData();
      });

      // User activity tracking
      this.trackUserActivity();
    },

    /**
     * Initialize battle buttons on page load
     */
    initializeBattleButtons: function () {
      // Add battle modal to body if not present
      if (!$("#qba-battle-modal").length) {
        this.loadBattleModal();
      }

      // Initialize leaderboard widgets
      this.initializeLeaderboardWidgets();
    },

    /**
     * Load battle modal HTML
     */
    loadBattleModal: function () {
      var self = this;

      $.ajax({
        url: qba_public_ajax.ajax_url,
        type: "POST",
        data: {
          action: "qba_get_battle_modal",
          nonce: qba_public_ajax.nonce,
        },
        success: function (response) {
          if (response.success) {
            $("body").append(response.data);
            // Initialize battle engine after modal is loaded
            if (typeof QBA_Battle_Engine !== "undefined") {
              QBA_Battle_Engine.init();
            }
          }
        },
      });
    },

    /**
     * Start a battle
     */
    startBattle: function (quizId, battleType) {
      // Store current quiz ID for battle engine
      qba_public_ajax.current_quiz_id = quizId;

      // Show battle modal
      if (typeof QBA_Battle_Engine !== "undefined") {
        QBA_Battle_Engine.showModal();
      } else {
        // Load modal first, then show
        this.loadBattleModal();
        // Modal will be shown after load
      }
    },

    /**
     * Initialize leaderboard widgets
     */
    initializeLeaderboardWidgets: function () {
      $(".qba-leaderboard-preview").each(function () {
        var $widget = $(this);
        var period = $widget.data("period") || "weekly";

        QBA_Public.loadLeaderboardWidget($widget, period);
      });
    },

    /**
     * Load leaderboard widget data
     */
    loadLeaderboardWidget: function ($widget, period) {
      $widget.html(
        '<div class="qba-loading">' +
          qba_public_ajax.strings.loading +
          "</div>",
      );

      $.ajax({
        url: qba_public_ajax.ajax_url,
        type: "POST",
        data: {
          action: "qba_get_leaderboard",
          period: period,
          limit: 5,
          nonce: qba_public_ajax.nonce,
        },
        success: function (response) {
          if (response.success) {
            QBA_Public.renderLeaderboardWidget($widget, response.data.data);
          } else {
            $widget.html(
              '<div class="qba-error">' +
                qba_public_ajax.strings.error +
                "</div>",
            );
          }
        },
        error: function () {
          $widget.html(
            '<div class="qba-error">' +
              qba_public_ajax.strings.error +
              "</div>",
          );
        },
      });
    },

    /**
     * Render leaderboard widget
     */
    renderLeaderboardWidget: function ($widget, data) {
      if (!data || data.length === 0) {
        $widget.html(
          '<div class="qba-no-data">' +
            qba_public_ajax.strings.no_leaderboard_data +
            "</div>",
        );
        return;
      }

      var html = '<ol class="qba-leaderboard-list">';
      $.each(data.slice(0, 5), function (index, player) {
        var isCurrentUser = player.user_id == qba_public_ajax.current_user_id;
        var cssClass = isCurrentUser ? "qba-current-user" : "";
        html +=
          '<li class="qba-leaderboard-item ' +
          cssClass +
          '">' +
          '<span class="qba-rank">#' +
          player.rank +
          "</span>" +
          '<span class="qba-player-name">' +
          player.display_name +
          "</span>" +
          '<span class="qba-player-score">' +
          player.total_points +
          " pts</span>" +
          "</li>";
      });
      html += "</ol>";

      $widget.html(html);
    },

    /**
     * Show leaderboard modal
     */
    showLeaderboardModal: function () {
      // Create or show leaderboard modal
      if (!$("#qba-leaderboard-modal").length) {
        this.createLeaderboardModal();
      }

      $("#qba-leaderboard-modal").fadeIn(300);
      $("body").addClass("qba-modal-open");

      this.loadLeaderboardData();
    },

    /**
     * Create leaderboard modal
     */
    createLeaderboardModal: function () {
      var modalHtml =
        '<div id="qba-leaderboard-modal" class="qba-modal-overlay" style="display: none;">' +
        '<div class="qba-modal-container qba-leaderboard-modal">' +
        '<div class="qba-modal-header">' +
        '<h2 class="qba-modal-title">' +
        qba_public_ajax.strings.leaderboard +
        "</h2>" +
        '<button type="button" class="qba-modal-close">&times;</button>' +
        "</div>" +
        '<div class="qba-modal-content">' +
        '<div class="qba-leaderboard-filters">' +
        '<select class="qba-period-select">' +
        '<option value="daily">' +
        qba_public_ajax.strings.today +
        "</option>" +
        '<option value="weekly" selected>' +
        qba_public_ajax.strings.this_week +
        "</option>" +
        '<option value="monthly">' +
        qba_public_ajax.strings.this_month +
        "</option>" +
        '<option value="alltime">' +
        qba_public_ajax.strings.all_time +
        "</option>" +
        "</select>" +
        "</div>" +
        '<div class="qba-leaderboard-content" id="qba-leaderboard-content">' +
        '<div class="qba-loading">' +
        qba_public_ajax.strings.loading +
        "</div>" +
        "</div>" +
        "</div>" +
        "</div>" +
        "</div>";

      $("body").append(modalHtml);

      // Bind modal events
      $("#qba-leaderboard-modal .qba-modal-close").on("click", function () {
        $("#qba-leaderboard-modal").fadeOut(300);
        $("body").removeClass("qba-modal-open");
      });

      $("#qba-leaderboard-modal .qba-period-select").on("change", function () {
        QBA_Public.loadLeaderboardData();
      });
    },

    /**
     * Load leaderboard data
     */
    loadLeaderboardData: function () {
      var period = $(".qba-period-select").val() || "weekly";
      var $content = $("#qba-leaderboard-content");

      $content.html(
        '<div class="qba-loading">' +
          qba_public_ajax.strings.loading +
          "</div>",
      );

      $.ajax({
        url: qba_public_ajax.ajax_url,
        type: "POST",
        data: {
          action: "qba_get_leaderboard",
          period: period,
          limit: 50,
          nonce: qba_public_ajax.nonce,
        },
        success: function (response) {
          if (response.success) {
            QBA_Public.renderLeaderboard($content, response.data);
          } else {
            $content.html(
              '<div class="qba-error">' +
                qba_public_ajax.strings.error +
                "</div>",
            );
          }
        },
        error: function () {
          $content.html(
            '<div class="qba-error">' +
              qba_public_ajax.strings.error +
              "</div>",
          );
        },
      });
    },

    /**
     * Render full leaderboard
     */
    renderLeaderboard: function ($container, data) {
      var stats = data.stats;
      var leaderboard = data.data;

      var html =
        '<div class="qba-leaderboard-stats">' +
        '<div class="qba-stat">' +
        '<span class="qba-stat-label">' +
        qba_public_ajax.strings.total_players +
        ":</span>" +
        '<span class="qba-stat-value">' +
        stats.total_players +
        "</span>" +
        "</div>" +
        '<div class="qba-stat">' +
        '<span class="qba-stat-label">' +
        qba_public_ajax.strings.total_battles +
        ":</span>" +
        '<span class="qba-stat-value">' +
        stats.total_battles +
        "</span>" +
        "</div>" +
        '<div class="qba-stat">' +
        '<span class="qba-stat-label">' +
        qba_public_ajax.strings.avg_rating +
        ":</span>" +
        '<span class="qba-stat-value">' +
        stats.avg_elo +
        "</span>" +
        "</div>" +
        "</div>";

      if (leaderboard && leaderboard.length > 0) {
        html +=
          '<table class="qba-leaderboard-table">' +
          "<thead>" +
          "<tr>" +
          "<th>" +
          qba_public_ajax.strings.rank +
          "</th>" +
          "<th>" +
          qba_public_ajax.strings.player +
          "</th>" +
          "<th>" +
          qba_public_ajax.strings.rating +
          "</th>" +
          "<th>" +
          qba_public_ajax.strings.wins +
          "</th>" +
          "<th>" +
          qba_public_ajax.strings.win_rate +
          "</th>" +
          "<th>" +
          qba_public_ajax.strings.streak +
          "</th>" +
          "</tr>" +
          "</thead>" +
          "<tbody>";

        $.each(leaderboard, function (index, player) {
          var isCurrentUser = player.user_id == qba_public_ajax.current_user_id;
          var cssClass = isCurrentUser ? "qba-current-user" : "";
          html +=
            '<tr class="' +
            cssClass +
            '">' +
            '<td class="qba-rank">#' +
            player.rank +
            "</td>" +
            '<td class="qba-player">' +
            player.display_name +
            "</td>" +
            '<td class="qba-rating">' +
            player.elo_rating +
            "</td>" +
            '<td class="qba-wins">' +
            player.battles_won +
            "</td>" +
            '<td class="qba-win-rate">' +
            player.win_rate +
            "%</td>" +
            '<td class="qba-streak">' +
            player.win_streak +
            "</td>" +
            "</tr>";
        });

        html += "</tbody></table>";
      } else {
        html +=
          '<div class="qba-no-data">' +
          qba_public_ajax.strings.no_battles_yet +
          "</div>";
      }

      $container.html(html);
    },

    /**
     * Track user activity
     */
    trackUserActivity: function () {
      // Track activity every 5 minutes
      setInterval(function () {
        $.ajax({
          url: qba_public_ajax.ajax_url,
          type: "POST",
          data: {
            action: "qba_track_activity",
            nonce: qba_public_ajax.nonce,
          },
        });
      }, 300000); // 5 minutes
    },

    /**
     * Show loading overlay
     */
    showLoading: function (message) {
      if (!$("#qba-loading-overlay").length) {
        $("body").append(
          '<div id="qba-loading-overlay" class="qba-loading-overlay"><div class="qba-loading-content"><div class="qba-loading-spinner"></div><div class="qba-loading-text">' +
            (message || qba_public_ajax.strings.loading) +
            "</div></div></div>",
        );
      }
      $("#qba-loading-overlay").fadeIn(200);
    },

    /**
     * Hide loading overlay
     */
    hideLoading: function () {
      $("#qba-loading-overlay").fadeOut(200);
    },
  };

  // Initialize when document is ready
  $(document).ready(function () {
    QBA_Public.init();
  });
})(jQuery);
