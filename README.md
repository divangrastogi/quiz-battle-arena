# Quiz Battle Arena

A competitive quiz battle system for WordPress that integrates with LearnDash to create engaging, real-time quiz competitions between users.

## Features

- **Real-time Battle System**: Challenge friends or join matchmaking queues for competitive quiz battles
- **LearnDash Integration**: Seamlessly works with existing LearnDash quizzes and courses
- **ELO Rating System**: Advanced skill-based matchmaking and ranking
- **Achievements & Badges**: Gamification elements to keep users engaged
- **Leaderboards**: Multiple time-based leaderboards (daily, weekly, monthly, all-time)
- **BuddyBoss Integration**: Social features including activity streams and profile enhancements
- **Responsive Design**: Mobile-friendly interface with smooth animations
- **Admin Dashboard**: Comprehensive admin tools for monitoring and managing battles

## Requirements

- WordPress 5.0+
- PHP 7.4+
- LearnDash LMS
- BuddyBoss Platform (optional, for social features)

## Installation

1. Download the plugin zip file
2. Go to WordPress Admin > Plugins > Add New
3. Click "Upload Plugin" and select the zip file
4. Click "Install Now" and then "Activate"
5. Configure settings at **Battle Arena > Settings**

## Quick Start

1. **Enable Battles on Quizzes**: Go to LearnDash > Quizzes and enable battle mode for selected quizzes
2. **Configure Settings**: Set battle timeouts, scoring rules, and matchmaking parameters
3. **Add Shortcodes**: Use `[qba_leaderboard]`, `[qba_user_stats]`, and `[qba_achievements]` on pages
4. **Users Start Battling**: Logged-in users can challenge friends or join matchmaking queues

## Shortcodes

### Leaderboard
```
[qba_leaderboard period="alltime" limit="50"]
```
- `period`: `daily`, `weekly`, `monthly`, `alltime` (default: `alltime`)
- `limit`: Number of players to show (default: `50`)

### User Statistics
```
[qba_user_stats user_id="123"]
```
- `user_id`: User ID to show stats for (default: current user)

### Achievements
```
[qba_achievements user_id="123"]
```
- `user_id`: User ID to show achievements for (default: current user)

## Settings

### General Settings
- **Enable Battles**: Turn the battle system on/off
- **Battle Timeout**: Maximum time allowed per battle (default: 15 minutes)
- **Questions per Battle**: Number of questions in each battle (default: 10)

### Scoring & Points
- **Points for Correct Answer**: Base points awarded (default: 10)
- **Time Bonus Multiplier**: Bonus points for quick answers (default: 0.5)
- **ELO K-Factor**: Rating change sensitivity (default: 32)

### Integrations
- **BuddyBoss Activity**: Post battle results to activity streams
- **Email Notifications**: Send battle invites and results via email
- **Push Notifications**: Browser notifications for battle events

## API Reference

### PHP Classes

#### QBA_Battle_Engine
Handles battle creation, management, and real-time logic.

```php
$engine = new QBA_Battle_Engine();

// Create a battle challenge
$battle_id = $engine->create_battle_challenge($quiz_id, $challenger_id, $opponent_id);

// Accept a challenge
$engine->accept_battle_challenge($battle_id, $user_id);

// Submit an answer
$engine->submit_battle_answer($battle_id, $question_id, $answer, $time_taken);
```

#### QBA_Queue_Manager
Manages matchmaking queues and opponent matching.

```php
$queue = new QBA_Queue_Manager();

// Join matchmaking queue
$queue_id = $queue->join_queue($user_id, $quiz_id);

// Leave queue
$queue->leave_queue($user_id);
```

#### QBA_Leaderboard
Handles leaderboard data and caching.

```php
$leaderboard = new QBA_Leaderboard();

// Get leaderboard data
$data = $leaderboard->get_leaderboard_data('alltime', 50);

// Get user position
$position = $leaderboard->get_user_position($user_id, 'alltime');
```

### JavaScript API

#### Battle Events
```javascript
// Listen for battle events
jQuery(document).on('qba_battle_started', function(e, data) {
    console.log('Battle started:', data);
});

jQuery(document).on('qba_battle_ended', function(e, data) {
    console.log('Battle ended:', data);
});
```

#### AJAX Endpoints
- `qba_create_battle`: Create a new battle
- `qba_join_queue`: Join matchmaking queue
- `qba_leave_queue`: Leave matchmaking queue
- `qba_accept_battle`: Accept battle challenge
- `qba_submit_answer`: Submit answer during battle
- `qba_get_battle_results`: Get battle results

## Database Tables

The plugin creates the following custom tables:

- `wp_qba_battles`: Battle records
- `wp_qba_battle_progress`: Individual battle progress
- `wp_qba_user_stats`: User statistics and ratings
- `wp_qba_user_badges`: Achievement badges
- `wp_qba_queue`: Matchmaking queue

## Hooks & Filters

### Actions
- `qba_battle_challenge_created`: Fires when a battle challenge is created
- `qba_battle_started`: Fires when a battle begins
- `qba_battle_completed`: Fires when a battle ends
- `qba_achievement_unlocked`: Fires when a user unlocks an achievement

### Filters
- `qba_battle_scoring`: Modify battle scoring logic
- `qba_elo_calculation`: Customize ELO rating calculations
- `qba_battle_timeout`: Adjust battle timeout settings
- `qba_leaderboard_data`: Filter leaderboard results

## Development

### Testing
Run the test suite:
```bash
composer install
./vendor/bin/phpunit
```

### Coding Standards
The plugin follows WordPress Coding Standards. Check code style:
```bash
./vendor/bin/phpcs .
```

### File Structure
```
quiz-battle-arena/
├── admin/                 # Admin interface
├── assets/                # CSS, JS, images
├── includes/              # Core classes
├── public/                # Public interface
├── tests/                 # Unit tests
├── composer.json          # Dependencies
├── phpunit.xml           # Test configuration
└── README.md             # This file
```

## Issues & Fixes

### Critical Issues
- **Indentation Violation**: Entire codebase uses spaces instead of tabs, violating WordPress Coding Standards. All files need indentation converted from spaces to tabs.

### Fixed Issues
- **Duplicate Shortcodes**: Removed duplicate shortcode registrations in main plugin file
- **Duplicate Functions**: Removed duplicate helper functions in qba-helper-functions.php
- **Unused Files**: Removed unused QBA_Core class file
- **Indentation**: Converted all spaces to tabs throughout codebase for WPCS compliance
- **PHPCS Violations**: Auto-fixed 5700+ coding standard violations
- **Syntax Errors**: Fixed PHP syntax error in class-qba-achievements.php (function outside class)
- **Admin Interface**: Created missing admin partial files (leaderboard, battles) and fixed settings page

### Remaining Tasks
- Minor PHPCS issues (comment formatting, file naming) - non-critical
- File splitting not needed (largest file is 760 lines, under 1000 limit)
- Verify all security measures (nonces, capabilities, sanitization)

## Support

For support and documentation, visit our [Knowledge Base](https://example.com/docs).

## Changelog

### 1.0.0
- Initial release
- Core battle system
- LearnDash integration
- ELO rating system
- Achievements and leaderboards
- BuddyBoss integration

## License

GPL-2.0-or-later

## Credits

Developed by WBCom Designs
