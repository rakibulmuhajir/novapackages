<?php

namespace App\Http\Remotes;

use App\CacheKeys;
use Github\Client as GitHubClient;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GitHub
{
    protected $github;

    public function __construct(GitHubClient $github)
    {
        $this->github = $github;
    }

    /**
     * Return user by username.
     *
     * @param  string $username GitHub username
     * @return array            User associative array
     */
    public function user($username)
    {
        // @todo: handle exceptions
        return Http::github()->get("/users/{$username}")->json();
    }

    /**
     * Get all issues labeled "suggestion".
     *
     * @return array of items
     */
    public function packageIdeaIssues()
    {
        return Cache::remember(CacheKeys::packageIdeaIssues(), 1, function () {
            $issues = collect($this->github->api('search')->issues('state:open label:package-idea repo:tighten/nova-package-development')['items']);

            return $this->sortIssuesByPositiveReactions($issues);
        });
    }

    protected function sortIssuesByPositiveReactions($issues)
    {
        return $issues->sortByDesc(function ($issue) {
            $countReactionTypes = collect($issue['reactions'])
                ->except(['url', 'total_count'])
                ->filter()
                ->count();

            return $countReactionTypes
             + Arr::get($issue, 'reactions.total_count')
             - (2 * Arr::get($issue, 'reactions.-1'))
             - Arr::get($issue, 'reactions.confused');
        });
    }

    public function api($api)
    {
        return $this->github->api($api);
    }

    public static function validateUrl($url): bool
    {
        return (bool) preg_match('/^https?:\/\/github.com\/([\w-]+)\/([\w-]+)/i', $url);
    }

    public function readme(string $repository): string|null
    {
        // @todo: handle exceptions
        $response = Http::github()
            ->withHeaders(['Accept' => 'application/vnd.github.html'])
            ->get("/repos/{$repository}/readme");

        if ($response->status() === 404) {
            return null;
        }

        return $response->body();
    }

    public function releases(string $repository): array
    {
        // @todo: handle exceptions
        return Http::github()
            ->withHeaders(['Accept' => 'application/vnd.github+json'])
            ->get("/repos/{$repository}/releases")
            ->json();
    }
}
