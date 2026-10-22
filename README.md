# BlogSync

BlogSync helps writers and content teams cross-publish newsletters from Substack to a WordPress.com site without copying and pasting. It fetches new posts from a Substack feed, cleans up the formatting, and publishes them to WordPress.com on a schedule. The app also includes a complete user authentication system so team members can log in and manage the sync.

## Description

A lightweight application that automates the boring work of republishing Substack newsletters to WordPress.com. It runs on a schedule, tracks which posts have already been synced, and handles the OAuth setup needed to post to WordPress.com.

## Features

- **Automated cross-publishing**: Copies new Substack posts to WordPress.com on a schedule.
- **RSS feed fetching**: Pulls posts from a Substack RSS feed with retry logic.
- **HTML cleanup**: Removes empty paragraphs and extra line breaks before publishing.
- **Sync tracking**: Records every post in a database with statuses like synced or failed.
- **Date filtering**: Only syncs posts published after a configurable cutoff date.
- **WordPress.com OAuth helper**: Built-in command to walk through the token exchange.
- **User authentication**: Registration, login, password reset, email verification, and profile management.
- **Dockerized cron runner**: Ready to deploy as a scheduled container.

## Installation

You will need PHP 8.3 or newer, Composer, Node.js, and npm installed.

1. Clone the repository:

```bash
git clone https://github.com/Ace-g-ops/extraction
cd extraction
```

2. Install PHP dependencies:

```bash
composer install
```

3. Create your environment file:

```bash
cp .env.example .env
```

4. Generate an application key and run the database migrations:

```bash
php artisan key:generate
php artisan migrate
```

5. Install front-end dependencies and build the assets:

```bash
npm install --ignore-scripts
npm run build
```

6. Configure your Substack and WordPress.com settings in `.env`:

```env
SUBSTACK_FEED_URL=https://yourname.substack.com/feed
WPCOM_SITE=yourwordpresssite.wordpress.com
WPCOM_CLIENT_ID=your_client_id
WPCOM_CLIENT_SECRET=your_client_secret
WPCOM_REDIRECT_URI=https://yourapp.test/wpcom-callback
SYNC_AFTER_DATE="2024-01-01"
WPCOM_POST_STATUS=publish
```

7. Authorize the app with WordPress.com:

```bash
php artisan wpcom:authorize
```

## Usage

After installation, you can run the sync manually:

```bash
php artisan sync:substack
```

The sync command fetches the Substack feed, cleans each post, and pushes it to WordPress.com. Posts are tracked in the database, so only new or previously failed posts are retried.

For production, the project includes a Laravel scheduler entry in `routes/console.php` that runs the sync every 15 minutes. On Render or another platform that supports cron jobs, the included `Dockerfile` starts the container and runs the sync command once per invocation.

To start the local web server and view the dashboard:

```bash
composer run dev
```

Then open `http://localhost` in your browser. You can register an account, log in, and manage your profile from the dashboard.

## Technologies Used

| Technology | Description |
|------------|-------------|
| [Laravel](https://laravel.com/) | PHP web framework |
| [PHP](https://www.php.net/) | Server-side language |
| [Vite](https://vitejs.dev/) | Front-end build tool |
| [WordPress.com REST API](https://developer.wordpress.com/docs/api/) | Publishing endpoint |

## Contributing

Pull requests are welcome. If you want to add a feature or fix a bug, please open an issue first so we can discuss the change. Make sure your code follows the existing style and passes the test suite.

## Author

- GitHub: [Ace-g-ops](https://github.com/Ace-g-ops)

## Badges

[![PHP](https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)](https://laravel.com/)
[![Vite](https://img.shields.io/badge/Vite-646CFF?style=for-the-badge&logo=vite&logoColor=white)](https://vitejs.dev/)

