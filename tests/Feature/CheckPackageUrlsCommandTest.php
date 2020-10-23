<?php

namespace Tests\Feature;

use App\Collaborator;
use App\Notifications\NotifyContributorOfInvalidPackageUrl;
use App\Package;
use App\Tag;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** @group integration */
class CheckPackageUrlsCommandTest extends TestCase
{

    use RefreshDatabase;

    public function setUp():void
    {
        parent::setUp();
        $this->validPackage = factory(Package::class)->create([
            'name' => 'Valid Package',
            'url' => 'https://github.com/tighten/novapackages',
            'repo_url' => 'https://github.com/tighten/novapackages',
            'author_id' => factory(Collaborator::class)->create([
                'user_id' => factory(User::class)->create()->id
            ])->id
        ]);

        $this->packageWithInvalidUrl = factory(Package::class)->create([
            'name' => 'Package with Invalid URL',
            'url' => 'https://github.com/some-dev/package-name',
            'repo_url' => 'https://github.com/some-dev/package-name',
            'author_id' => factory(Collaborator::class)->create([
                'user_id' => factory(User::class)->create()->id
            ])->id
        ]);

        $this->packageWithInvalidDomain = factory(Package::class)->create([
            'name' => 'Package with Invalid Domain',
            'url' => 'https://not-github.com/other-dev/package-name',
            'repo_url' => 'https://github.com/tighten/novapackages',
        ]);
    }

    /**
     * @test
     */
    public function calling_command_tags_invalid_packages_with_404_tag()
    {
        $this->artisan('check:package-urls');

        $this->assertEquals($this->validPackage->tags()->count(), 0);
        $this->assertFalse($this->validPackage->tags()->where('tags.name', '404 error')->exists());
        $this->get(route(
            'packages.show',
            [
                $this->validPackage->composer_vendor,
                $this->validPackage->composer_package
            ]
        ))->assertDontSee('404 Error');

        $this->assertEquals($this->packageWithInvalidUrl->tags()->count(), 1);
        $this->assertTrue($this->packageWithInvalidUrl->tags()->where('tags.name', '404 error')->exists());
        $this->get(route(
            'packages.show',
            [
                $this->packageWithInvalidUrl->composer_vendor,
                $this->packageWithInvalidUrl->composer_package
            ]
        ))->assertSee('404 Error');

        $this->assertEquals($this->packageWithInvalidDomain->tags()->count(), 1);
        $this->assertTrue($this->packageWithInvalidDomain->tags()->where('tags.name', '404 error')->exists());
        $this->get(route(
            'packages.show',
            [
                $this->packageWithInvalidDomain->composer_vendor,
                $this->packageWithInvalidDomain->composer_package
            ]
        ))->assertSee('404 Error');
    }

    /**
     * @test
     */
    public function calling_command_sends_notification_to_author_and_contributors_of_invalid_packages()
    {
        Notification::fake();

        $this->artisan('check:package-urls');

        Notification::assertNotSentTo(
            $this->validPackage->author->user,
            NotifyContributorOfInvalidPackageUrl::class
        );

        Notification::assertSentTo(
            $this->packageWithInvalidUrl->author->user,
            NotifyContributorOfInvalidPackageUrl::class,
        );
    }

    /**
     * @test
     */
    public function command_ignores_packages_that_already_have_404_tag()
    {
        Notification::fake();

        $errorTag = factory(Tag::class)->create([
            'name' => '404 error',
            'slug' => '404-error'
        ]);
        $this->packageWithInvalidUrl->tags()->sync($errorTag);

        $this->artisan('check:package-urls');

        Notification::assertNotSentTo(
            $this->packageWithInvalidUrl->author->user,
            NotifyContributorOfInvalidPackageUrl::class,
        );
    }
}
