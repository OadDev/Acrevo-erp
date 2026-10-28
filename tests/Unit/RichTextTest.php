<?php

namespace Tests\Unit;

use App\Support\RichText;
use Tests\TestCase;

class RichTextTest extends TestCase
{
    public function test_blank_input_returns_null(): void
    {
        $this->assertNull(RichText::sanitize(null));
        $this->assertNull(RichText::sanitize(''));
        $this->assertNull(RichText::sanitize('   '));
    }

    public function test_allowed_formatting_tags_are_preserved(): void
    {
        $html = '<h1>Heading</h1><p>Some <strong>bold</strong> and <em>italic</em> text.</p><ul><li>One</li><li>Two</li></ul><ol><li>First</li></ol><p style="text-align: center;">Centered</p>';

        $clean = RichText::sanitize($html);

        $this->assertStringContainsString('<h1>Heading</h1>', $clean);
        $this->assertStringContainsString('<strong>bold</strong>', $clean);
        $this->assertStringContainsString('<em>italic</em>', $clean);
        $this->assertStringContainsString('<li>One</li>', $clean);
        $this->assertStringContainsString('text-align:center', str_replace(' ', '', $clean));
    }

    public function test_script_tags_and_event_handlers_are_stripped(): void
    {
        $html = '<p onclick="alert(1)">Hi</p><script>alert(1)</script><img src="x.png" onerror="alert(2)">';

        $clean = RichText::sanitize($html);

        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('onerror', $clean);
        $this->assertStringNotContainsString('<img', $clean);
        $this->assertStringContainsString('Hi', $clean);
    }

    public function test_plain_text_with_stray_angle_brackets_is_escaped_not_dropped(): void
    {
        $clean = RichText::sanitize('The pipe size is <2 inch');

        $this->assertStringContainsString('&lt;2 inch', $clean);
    }
}
