<?php

namespace Tests\Feature;

use App\Services\AI\AIExecutor;
use App\Services\AI\SystemPromptBuilder;
use Tests\TestCase;

/**
 * Guards the security invariant that the AI cannot run raw shell commands.
 * The "shell" tool was removed from the AI's action set; any attempt to use it
 * must be rejected before anything executes.
 *
 * (The Web Terminal — where the operator types commands themselves — is a
 * separate feature and is not covered/affected by this test.)
 */
class AiShellDisabledTest extends TestCase
{
    public function test_shell_is_not_an_available_ai_tool(): void
    {
        $this->assertArrayNotHasKey('shell', AIExecutor::TOOLS);
    }

    public function test_assessing_a_shell_action_is_blocked(): void
    {
        $result = AIExecutor::assess('shell', ['command' => 'ls -la /']);

        $this->assertFalse($result['allowed']);
        $this->assertSame('blocked', $result['level']);
    }

    public function test_running_a_shell_action_is_rejected_before_dispatch(): void
    {
        $result = AIExecutor::run('shell', ['command' => 'whoami']);

        $this->assertFalse($result['ok']);
        // The rejection message comes from assess() (the guard), NOT from
        // dispatch() — proving the request never reaches the command runner.
        $this->assertStringContainsStringIgnoringCase('disabled', $result['output']);
        $this->assertStringNotContainsString('root', $result['output']);
    }

    public function test_system_prompt_does_not_declare_a_shell_tool(): void
    {
        $prompt = SystemPromptBuilder::build('execute');

        // The tool list must still be present (e.g. read_file) but must NOT
        // advertise a "shell" tool the AI could pick.
        $this->assertStringContainsString('- read_file', $prompt);
        $this->assertStringNotContainsString('- shell', $prompt);
        $this->assertStringNotContainsString('"tool":"shell"', $prompt);
        $this->assertStringNotContainsString('"tool": "shell"', $prompt);
    }

    public function test_other_ai_tools_are_still_available(): void
    {
        // A representative vetted tool must still assess as allowed, proving the
        // rest of the AI action set was not broken by removing shell.
        $result = AIExecutor::assess('read_file', ['path' => '/etc/hostname']);

        $this->assertTrue($result['allowed']);
        $this->assertNotSame('blocked', $result['level']);
    }
}
