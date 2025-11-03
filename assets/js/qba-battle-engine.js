/**
 * Battle Engine JavaScript
 *
 * Handles real-time battle functionality and UI interactions
 *
 * @package QuizBattleArena
 * @since 1.0.0
 */

(function ($) {
  "use strict";

  /**
   * QBA Battle Engine
   */
  window.QBA_Battle_Engine = {
    // Current battle state
    currentBattle: null,
    currentQuestion: null,
    battleTimer: null,
    questionTimer: null,
    battleStartTime: null,
    questionStartTime: null,

    // DOM elements
    $modal: null,
    $setupScreen: null,
    $activeScreen: null,
    $resultsScreen: null,
    $challengeScreen: null,

    /**
     * Initialize the battle engine
     */
    init: function () {
      this.$modal = $("#qba-battle-modal");
      this.$setupScreen = $("#qba-battle-setup");
      this.$activeScreen = $("#qba-battle-active");
      this.$resultsScreen = $("#qba-battle-results");
      this.$challengeScreen = $("#qba-battle-challenge");

      this.bindEvents();
      this.loadFriendsList();
    },

    /**
     * Bind event handlers
     */
    bindEvents: function () {
      var self = this;

      // Modal controls
      this.$modal.on("click", ".qba-modal-close", function () {
        self.closeModal();
      });

      // Battle setup
      this.$modal.on("click", ".qba-start-quick-battle", function () {
        self.startQuickBattle();
      });

      this.$modal.on("change", "#qba-friend-select", function () {
        var friendId = $(this).val();
        $(".qba-challenge-friend-btn").prop("disabled", !friendId);
      });

      this.$modal.on("click", ".qba-challenge-friend-btn", function () {
        var friendId = $("#qba-friend-select").val();
        if (friendId) {
          self.challengeFriend(friendId);
        }
      });

      // Queue management
      this.$modal.on("click", ".qba-leave-queue-btn", function () {
        self.leaveQueue();
      });

      // Battle actions
      this.$modal.on("click", ".qba-submit-answer-btn", function () {
        self.submitAnswer();
      });

      this.$modal.on("change", 'input[name="qba-answer"]', function () {
        $(".qba-submit-answer-btn").prop("disabled", false);
      });

      // Challenge actions
      this.$modal.on("click", ".qba-accept-challenge-btn", function () {
        self.acceptChallenge();
      });

      this.$modal.on("click", ".qba-decline-challenge-btn", function () {
        self.declineChallenge();
      });

      // Results actions
      this.$modal.on("click", ".qba-play-again-btn", function () {
        self.resetBattle();
      });

      // Keyboard shortcuts
      $(document).on("keydown", function (e) {
        if (!self.$modal.is(":visible")) return;

        // ESC to close modal
        if (e.keyCode === 27) {
          self.closeModal();
        }

        // Number keys for answer selection (1-4)
        if (
          self.$activeScreen.is(":visible") &&
          e.keyCode >= 49 &&
          e.keyCode <= 52
        ) {
          var answerIndex = e.keyCode - 49;
          var $answerOption = $(".qba-answer-option").eq(answerIndex);
          if ($answerOption.length) {
            $answerOption
              .find('input[name="qba-answer"]')
              .prop("checked", true)
              .trigger("change");
          }
        }

        // Enter to submit answer
        if (self.$activeScreen.is(":visible") && e.keyCode === 13) {
          if (!$(".qba-submit-answer-btn").prop("disabled")) {
            self.submitAnswer();
          }
        }
      });
    },

    /**
     * Trap focus within modal for accessibility
     */
    trapFocus: function () {
      var self = this;
      var $focusableElements = this.$modal.find(
        'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])',
      );
      var $firstFocusable = $focusableElements.first();
      var $lastFocusable = $focusableElements.last();

      this.$modal.on("keydown.qba-focus-trap", function (e) {
        if (e.keyCode !== 9) return; // Tab key

        if (e.shiftKey) {
          // Shift + Tab
          if (document.activeElement === $firstFocusable[0]) {
            $lastFocusable.focus();
            e.preventDefault();
          }
        } else {
          // Tab
          if (document.activeElement === $lastFocusable[0]) {
            $firstFocusable.focus();
            e.preventDefault();
          }
        }
      });
    },

    /**
     * Show the battle modal
     */
    showModal: function () {
      this.$modal.fadeIn(300);
      $("body").addClass("qba-modal-open");
      this.showSetupScreen();

      // Focus management for accessibility
      this.$modal.attr("aria-hidden", "false");
      var $firstFocusable = this.$modal
        .find(
          'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])',
        )
        .first();
      if ($firstFocusable.length) {
        $firstFocusable.focus();
      }

      // Trap focus within modal
      this.trapFocus();
    },

    /**
     * Close the battle modal
     */
    closeModal: function () {
      if (this.currentBattle && this.currentBattle.status === "active") {
        if (
          !confirm(
            qba_public_ajax.strings.confirm_leave_battle ||
              "Are you sure you want to leave the battle?",
          )
        ) {
          return;
        }
      }

      this.$modal.fadeOut(300);
      $("body").removeClass("qba-modal-open");
      this.$modal.attr("aria-hidden", "true");
      this.resetBattle();

      // Restore focus to the element that opened the modal
      if (this.lastFocusedElement) {
        this.lastFocusedElement.focus();
      }
    },

    /**
     * Show setup screen
     */
    showSetupScreen: function () {
      this.hideAllScreens();
      this.$setupScreen.show();
    },

    /**
     * Show active battle screen
     */
    showActiveScreen: function () {
      this.hideAllScreens();
      this.$activeScreen.show();
    },

    /**
     * Show results screen
     */
    showResultsScreen: function () {
      this.hideAllScreens();
      this.$resultsScreen.show();
    },

    /**
     * Show challenge screen
     */
    showChallengeScreen: function () {
      this.hideAllScreens();
      this.$challengeScreen.show();
    },

    /**
     * Hide all screens
     */
    hideAllScreens: function () {
      this.$setupScreen.hide();
      this.$activeScreen.hide();
      this.$resultsScreen.hide();
      this.$challengeScreen.hide();
    },

    /**
     * Load friends list for challenging
     */
    loadFriendsList: function () {
      var self = this;

      // For BuddyBoss integration, load friends
      if (typeof bp_get_friends !== "undefined") {
        // BuddyBoss friends API
        $.ajax({
          url: qba_public_ajax.ajax_url,
          type: "POST",
          data: {
            action: "qba_get_friends",
            nonce: qba_public_ajax.nonce,
          },
          success: function (response) {
            if (response.success) {
              self.populateFriendsList(response.data);
            }
          },
        });
      } else {
        // Fallback: load recent opponents or active users
        $.ajax({
          url: qba_public_ajax.ajax_url,
          type: "POST",
          data: {
            action: "qba_get_recent_opponents",
            nonce: qba_public_ajax.nonce,
          },
          success: function (response) {
            if (response.success) {
              self.populateFriendsList(response.data);
            }
          },
        });
      }
    },

    /**
     * Populate friends select dropdown
     */
    populateFriendsList: function (friends) {
      var $select = $("#qba-friend-select");
      $select.empty();
      $select.append(
        '<option value="">' +
          qba_public_ajax.strings.select_friend +
          "</option>",
      );

      $.each(friends, function (index, friend) {
        $select.append(
          '<option value="' + friend.id + '">' + friend.name + "</option>",
        );
      });
    },

    /**
     * Start a quick battle (random opponent)
     */
    startQuickBattle: function () {
      var self = this;

      this.showQueueStatus();

      $.ajax({
        url: qba_public_ajax.ajax_url,
        type: "POST",
        data: {
          action: "qba_join_queue",
          quiz_id: qba_public_ajax.current_quiz_id,
          nonce: qba_public_ajax.nonce,
        },
        success: function (response) {
          if (response.success) {
            self.startQueueTimer();
            self.pollForMatch();
          } else {
            self.showSetupScreen();
            alert(response.data || qba_public_ajax.strings.error);
          }
        },
        error: function () {
          self.showSetupScreen();
          alert(qba_public_ajax.strings.error);
        },
      });
    },

    /**
     * Challenge a specific friend
     */
    challengeFriend: function (friendId) {
      var self = this;

      $.ajax({
        url: qba_public_ajax.ajax_url,
        type: "POST",
        data: {
          action: "qba_create_battle",
          quiz_id: qba_public_ajax.current_quiz_id,
          opponent_id: friendId,
          battle_type: "direct",
          nonce: qba_public_ajax.nonce,
        },
        success: function (response) {
          if (response.success) {
            alert(qba_public_ajax.strings.challenge_sent);
            self.closeModal();
          } else {
            alert(response.data || qba_public_ajax.strings.error);
          }
        },
        error: function () {
          alert(qba_public_ajax.strings.error);
        },
      });
    },

    /**
     * Show queue status
     */
    showQueueStatus: function () {
      $("#qba-queue-status").show();
      $(".qba-battle-options").hide();
    },

    /**
     * Hide queue status
     */
    hideQueueStatus: function () {
      $("#qba-queue-status").hide();
      $(".qba-battle-options").show();
    },

    /**
     * Start queue timer
     */
    startQueueTimer: function () {
      var self = this;
      var seconds = 0;

      this.queueTimer = setInterval(function () {
        seconds++;
        var minutes = Math.floor(seconds / 60);
        var remainingSeconds = seconds % 60;
        $(".qba-queue-timer").text(
          (minutes < 10 ? "0" : "") +
            minutes +
            ":" +
            (remainingSeconds < 10 ? "0" : "") +
            remainingSeconds,
        );

        // Auto-leave queue after timeout
        if (seconds >= qba_public_ajax.settings.queue_timeout) {
          self.leaveQueue();
        }
      }, 1000);
    },

    /**
     * Stop queue timer
     */
    stopQueueTimer: function () {
      if (this.queueTimer) {
        clearInterval(this.queueTimer);
        this.queueTimer = null;
      }
    },

    /**
     * Poll for matchmaking match
     */
    pollForMatch: function () {
      var self = this;

      this.matchPollTimer = setInterval(function () {
        $.ajax({
          url: qba_public_ajax.ajax_url,
          type: "POST",
          data: {
            action: "qba_check_queue_match",
            nonce: qba_public_ajax.nonce,
          },
          success: function (response) {
            if (response.success && response.data.battle_id) {
              self.stopQueueTimer();
              clearInterval(self.matchPollTimer);
              self.startBattle(response.data.battle_id);
            }
          },
        });
      }, 2000); // Poll every 2 seconds
    },

    /**
     * Leave matchmaking queue
     */
    leaveQueue: function () {
      var self = this;

      this.stopQueueTimer();
      if (this.matchPollTimer) {
        clearInterval(this.matchPollTimer);
      }

      $.ajax({
        url: qba_public_ajax.ajax_url,
        type: "POST",
        data: {
          action: "qba_leave_queue",
          nonce: qba_public_ajax.nonce,
        },
        success: function (response) {
          self.hideQueueStatus();
          self.showSetupScreen();
        },
      });
    },

    /**
     * Start a battle
     */
    startBattle: function (battleId) {
      this.currentBattle = { id: battleId, status: "active" };
      this.battleStartTime = Date.now();

      this.showActiveScreen();
      this.initializeBattleUI();
      this.loadNextQuestion();
      this.startBattleTimer();
    },

    /**
     * Initialize battle UI
     */
    initializeBattleUI: function () {
      $("#qba-current-question").text("1");
      $("#qba-total-questions").text(qba_public_ajax.settings.max_questions);
      this.updatePlayerScores(0, 0);
      this.updatePlayerProgress(0, 0);
    },

    /**
     * Start battle timer
     */
    startBattleTimer: function () {
      var self = this;
      var remainingTime = qba_public_ajax.settings.battle_timeout;

      this.battleTimer = setInterval(function () {
        remainingTime--;

        var minutes = Math.floor(remainingTime / 60);
        var seconds = remainingTime % 60;
        $("#qba-battle-timer").text(
          (minutes < 10 ? "0" : "") +
            minutes +
            ":" +
            (seconds < 10 ? "0" : "") +
            seconds,
        );

        if (remainingTime <= 0) {
          self.endBattle("timeout");
        }
      }, 1000);
    },

    /**
     * Load next question
     */
    loadNextQuestion: function () {
      var self = this;

      $.ajax({
        url: qba_public_ajax.ajax_url,
        type: "POST",
        data: {
          action: "qba_get_next_question",
          battle_id: this.currentBattle.id,
          nonce: qba_public_ajax.nonce,
        },
        success: function (response) {
          if (response.success) {
            self.displayQuestion(response.data);
            self.startQuestionTimer();
          } else {
            // No more questions, end battle
            self.endBattle("completed");
          }
        },
      });
    },

    /**
     * Display question
     */
    displayQuestion: function (questionData) {
      this.currentQuestion = questionData;
      this.questionStartTime = Date.now();

      $("#qba-question-text").text(questionData.question);

      var $answerOptions = $("#qba-answer-options");
      $answerOptions.empty();

      $.each(questionData.answers, function (index, answer) {
        var $option = $('<div class="qba-answer-option">').append(
          $("<label>").append(
            $("<input>", {
              type: "radio",
              name: "qba-answer",
              value: answer.value,
            }),
            $('<span class="qba-answer-text">').text(answer.text),
          ),
        );
        $answerOptions.append($option);
      });

      $(".qba-submit-answer-btn").prop("disabled", true);
    },

    /**
     * Start question timer
     */
    startQuestionTimer: function () {
      var self = this;
      var timeLimit = 30; // 30 seconds per question
      var remainingTime = timeLimit;

      this.questionTimer = setInterval(function () {
        remainingTime--;

        if (remainingTime <= 0) {
          self.submitAnswer(true); // Auto-submit with no answer
        }
      }, 1000);
    },

    /**
     * Submit answer
     */
    submitAnswer: function (autoSubmit) {
      if (this.questionTimer) {
        clearInterval(this.questionTimer);
      }

      var answer = $('input[name="qba-answer"]:checked').val() || "";
      var timeTaken = (Date.now() - this.questionStartTime) / 1000;

      var self = this;

      $.ajax({
        url: qba_public_ajax.ajax_url,
        type: "POST",
        data: {
          action: "qba_submit_answer",
          battle_id: this.currentBattle.id,
          question_id: this.currentQuestion.id,
          answer: answer,
          time_taken: timeTaken,
          nonce: qba_public_ajax.nonce,
        },
        success: function (response) {
          if (response.success) {
            self.handleAnswerResult(response.data);
          } else {
            alert(response.data || qba_public_ajax.strings.error);
          }
        },
      });
    },

    /**
     * Handle answer submission result
     */
    handleAnswerResult: function (result) {
      // Update UI with result
      var currentQuestion = parseInt($("#qba-current-question").text());
      $("#qba-current-question").text(currentQuestion + 1);

      // Update scores
      this.updatePlayerScores(result.user_score, result.opponent_score || 0);

      // Show feedback
      this.showAnswerFeedback(result.correct);

      // Load next question after delay
      var self = this;
      setTimeout(function () {
        self.loadNextQuestion();
      }, 2000);
    },

    /**
     * Show answer feedback
     */
    showAnswerFeedback: function (isCorrect) {
      var feedbackClass = isCorrect ? "qba-correct" : "qba-incorrect";
      var feedbackText = isCorrect
        ? qba_public_ajax.strings.correct
        : qba_public_ajax.strings.incorrect;

      // Add visual feedback to selected answer
      $('input[name="qba-answer"]:checked')
        .closest(".qba-answer-option")
        .addClass(feedbackClass);

      // Show feedback message
      $("#qba-question-display").append(
        $('<div class="qba-answer-feedback ' + feedbackClass + '">').text(
          feedbackText,
        ),
      );
    },

    /**
     * Update player scores
     */
    updatePlayerScores: function (userScore, opponentScore) {
      $(".qba-player-you .qba-player-score").text(userScore + " pts");
      $(".qba-player-opponent .qba-player-score").text(opponentScore + " pts");
    },

    /**
     * Update player progress
     */
    updatePlayerProgress: function (userProgress, opponentProgress) {
      $(".qba-player-you .qba-progress-fill").css("width", userProgress + "%");
      $(".qba-player-opponent .qba-progress-fill").css(
        "width",
        opponentProgress + "%",
      );
    },

    /**
     * End battle
     */
    endBattle: function (reason) {
      if (this.battleTimer) {
        clearInterval(this.battleTimer);
      }
      if (this.questionTimer) {
        clearInterval(this.questionTimer);
      }

      this.loadBattleResults();
    },

    /**
     * Load battle results
     */
    loadBattleResults: function () {
      var self = this;

      $.ajax({
        url: qba_public_ajax.ajax_url,
        type: "POST",
        data: {
          action: "qba_get_battle_results",
          battle_id: this.currentBattle.id,
          nonce: qba_public_ajax.nonce,
        },
        success: function (response) {
          if (response.success) {
            self.displayBattleResults(response.data);
          }
        },
      });
    },

    /**
     * Display battle results
     */
    displayBattleResults: function (results) {
      this.showResultsScreen();

      // Update scores
      $("#qba-your-final-score").text(results.challenger_score + " pts");
      $("#qba-opponent-final-score").text(results.opponent_score + " pts");

      // Update accuracy
      $("#qba-your-accuracy").text(results.challenger_accuracy + "%");
      $("#qba-opponent-accuracy").text(results.opponent_accuracy + "%");

      // Update status
      var yourStatus = results.is_winner
        ? qba_public_ajax.strings.you_win
        : qba_public_ajax.strings.you_lose;
      var opponentStatus = results.is_winner
        ? qba_public_ajax.strings.you_lose
        : qba_public_ajax.strings.you_win;

      $("#qba-your-status")
        .text(yourStatus)
        .toggleClass("qba-winner", results.is_winner);
      $("#qba-opponent-status")
        .text(opponentStatus)
        .toggleClass("qba-winner", !results.is_winner);
    },

    /**
     * Accept battle challenge
     */
    acceptChallenge: function () {
      var self = this;

      $.ajax({
        url: qba_public_ajax.ajax_url,
        type: "POST",
        data: {
          action: "qba_accept_battle",
          battle_id: this.currentBattle.id,
          nonce: qba_public_ajax.nonce,
        },
        success: function (response) {
          if (response.success) {
            self.startBattle(response.data.battle_id);
          } else {
            alert(response.data || qba_public_ajax.strings.error);
          }
        },
      });
    },

    /**
     * Show the battle modal
     */
    showModal: function () {
      // Store the currently focused element to restore later
      this.lastFocusedElement = document.activeElement;

      this.$modal.fadeIn(300);
      $("body").addClass("qba-modal-open");
      this.showSetupScreen();

      // Focus management for accessibility
      this.$modal.attr("aria-hidden", "false");
      var $firstFocusable = this.$modal
        .find(
          'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])',
        )
        .first();
      if ($firstFocusable.length) {
        $firstFocusable.focus();
      }

      // Trap focus within modal
      this.trapFocus();
    },

    /**
     * Reset battle state
     */
    resetBattle: function () {
      this.currentBattle = null;
      this.currentQuestion = null;
      this.battleStartTime = null;
      this.questionStartTime = null;

      if (this.battleTimer) {
        clearInterval(this.battleTimer);
        this.battleTimer = null;
      }

      if (this.questionTimer) {
        clearInterval(this.questionTimer);
        this.questionTimer = null;
      }

      this.stopQueueTimer();

      this.showSetupScreen();
    },
  };

  // Initialize when document is ready
  $(document).ready(function () {
    QBA_Battle_Engine.init();
  });
})(jQuery);
