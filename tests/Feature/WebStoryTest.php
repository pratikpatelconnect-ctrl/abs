<?php

namespace Tests\Feature;

use Tests\TestCase;

class WebStoryTest extends TestCase
{
    public function test_top_programming_languages_story_is_accessible(): void
    {
        $response = $this->get('/web-stories/top-programming-languages');

        $response->assertOk();
        $response->assertSee('<amp-story', false);
        $response->assertSee('Top 10 Programming Languages');
    }
}
