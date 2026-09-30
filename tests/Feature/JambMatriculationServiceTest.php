<?php

namespace Tests\Feature;

use App\Services\Jamb\JambMatriculationVerificationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The JAMB portal intermittently serves a degraded copy of the
 * CheckMatriculationList page: the ASP.NET state fields are present but
 * stub-sized and the examination dropdown contains no options. Posting the
 * search from that state is rejected by the portal, which answers with its
 * generic "error Handle" page instead of a matriculation result.
 *
 * These tests pin the two halves of the fix: the degraded page is never used as
 * the basis for a postback, and the portal's error page is never reported to a
 * student as a negative answer about their registration number.
 */
class JambMatriculationServiceTest extends TestCase
{
    /** A real, fully populated portal page. */
    private function goodPage(): string
    {
        $options = '';
        foreach ([2009, 2020, 2024, 2025, 2026] as $year) {
            $options .= "<option value=\"{$year}\">{$year} Unified Tertiary Matriculation Examination (UTME)</option>";
        }

        return <<<HTML
        <!DOCTYPE html><html><head><title>Matriculation List...</title></head><body>
        <form method="post" action="./CheckMatriculationList" id="ctl23">
        <input type="hidden" name="__VIEWSTATE" id="__VIEWSTATE" value="{$this->viewstate('good')}" />
        <input type="hidden" name="__VIEWSTATEGENERATOR" id="__VIEWSTATEGENERATOR" value="54E68CC8" />
        <input type="hidden" name="__EVENTVALIDATION" id="__EVENTVALIDATION" value="{$this->viewstate('event')}" />
        <select id="ddlExamination" name="ddlExamination">
        <option value="">Select Examination...</option>
        {$options}
        </select>
        <input type="text" name="txtRegNumber" id="txtRegNumber" />
        <a id="lnkSearch" href="javascript:__doPostBack('lnkSearch','')">Fetch My Details</a>
        </form></body></html>
        HTML;
    }

    /** The degraded copy: state fields present but tiny, dropdown empty. */
    private function degradedPage(): string
    {
        return <<<HTML
        <!DOCTYPE html><html><head><title>Matriculation List...</title></head><body>
        <form method="post" action="./CheckMatriculationList" id="ctl23">
        <input type="hidden" name="__VIEWSTATE" id="__VIEWSTATE" value="s8D8f9wLEEMFQQ9vgklmT6YjBHtuXOHvwZBDh3MXpaqg1TVQN9Oy" />
        <input type="hidden" name="__VIEWSTATEGENERATOR" id="__VIEWSTATEGENERATOR" value="54E68CC8" />
        <input type="hidden" name="__EVENTVALIDATION" id="__EVENTVALIDATION" value="BLQXogqi1NFKHwmYjWxW5hwLSvNKj1QasD2LBs3A6bnOJ6GCHtbY" />
        <select id="ddlExamination" name="ddlExamination">
        <option value="">Select Examination...</option>
        </select>
        <input type="text" name="txtRegNumber" id="txtRegNumber" />
        <a id="lnkSearch" href="javascript:__doPostBack('lnkSearch','')">Fetch My Details</a>
        </form></body></html>
        HTML;
    }

    /** The portal's generic failure page, served in place of a result. */
    private function portalErrorPage(): string
    {
        return <<<HTML
        <!DOCTYPE html><html><head><title>JAMB e-Facility - error Handle</title></head><body>
        <h1>We are sorry</h1>
        <p>The page you are looking for might have been removed, had its name changed,
        or had processing error or is temporarily unavailable.</p>
        </body></html>
        HTML;
    }

    private function successPage(): string
    {
        return <<<HTML
        <!DOCTYPE html><html><body>
        <p>Congratulations, you are on the Matriculation List</p>
        <div class="profile-info"><h1>Test Candidate</h1></div>
        <a>Institution: <span>Test University</span></a>
        <a>Programme: <span>Cyber Security</span></a>
        </body></html>
        HTML;
    }

    private function viewstate(string $kind): string
    {
        return $kind === 'good' ? str_repeat('A', 3000) : str_repeat('B', 140);
    }

    /**
     * The examination-option list is cached for an hour against the shared
     * cache store, so a stale entry from an earlier test would decide the
     * outcome instead of the faked response.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Cache::forget('jamb_exam_options');
    }

    public function test_a_verified_candidate_is_returned_with_their_record(): void
    {
        Http::fake([
            'efacility.jamb.gov.ng/CheckMatriculationList' => Http::sequence()
                // 1. the examination-option list
                ->push($this->goodPage(), 200)
                // 2. a fresh page to post back from
                ->push($this->goodPage(), 200)
                // 3. the result
                ->push($this->successPage(), 200),
        ]);

        $result = app(JambMatriculationVerificationService::class)
            ->verify(2025, '202551494080CF', 'UTME');

        $this->assertTrue($result['verified'], 'Expected the candidate to verify.');
        $this->assertSame('Test Candidate', $result['name']);
        $this->assertSame('Test University', $result['institution']);
        $this->assertSame('Cyber Security', $result['programme']);
    }

    public function test_a_degraded_page_is_retried_instead_of_used_for_the_postback(): void
    {
        // The option list is degraded twice, then the search form is degraded
        // twice, then a good page, then the result. The search must be posted
        // from the good page and must never carry a stub viewstate.
        Http::fake([
            'efacility.jamb.gov.ng/CheckMatriculationList' => Http::sequence()
                ->push($this->degradedPage(), 200)
                ->push($this->degradedPage(), 200)
                ->push($this->goodPage(), 200)
                ->push($this->degradedPage(), 200)
                ->push($this->degradedPage(), 200)
                ->push($this->goodPage(), 200)
                ->push($this->successPage(), 200),
        ]);

        $result = app(JambMatriculationVerificationService::class)
            ->verify(2025, '202551494080CF', 'UTME');

        $this->assertTrue($result['verified']);

        // No postback may ever carry the stub viewstate.
        Http::assertSent(function ($request) {
            return ! str_contains((string) $request->body(), str_repeat('B', 140));
        });
    }

    public function test_persistently_degraded_pages_fail_as_a_provider_problem_not_a_bad_student(): void
    {
        // Never a usable page. The candidate must not be told their number is
        // invalid: a provider fault is not evidence about the student.
        Http::fake([
            'efacility.jamb.gov.ng/CheckMatriculationList' => Http::response($this->degradedPage(), 200),
        ]);

        $result = app(JambMatriculationVerificationService::class)
            ->verify(2025, '202551494080CF', 'UTME');

        $this->assertFalse($result['verified']);
        $this->assertNotSame('not_found', $result['status']);
        $this->assertContains($result['status'], ['provider_unavailable', 'temporary_failure']);
    }

    public function test_the_portal_error_page_is_never_reported_as_a_negative_answer(): void
    {
        Http::fake([
            'efacility.jamb.gov.ng/CheckMatriculationList' => Http::sequence()
                ->push($this->goodPage(), 200)
                ->push($this->goodPage(), 200)
                ->push($this->portalErrorPage(), 200),
        ]);

        $result = app(JambMatriculationVerificationService::class)
            ->verify(2025, '202551494080CF', 'UTME');

        $this->assertFalse($result['verified']);
        $this->assertNotSame('not_found', $result['status'], 'A portal error is not proof the candidate is unregistered.');
    }

    public function test_a_portal_error_answer_is_retried_rather_than_reported_as_ambiguous(): void
    {
        // [1] option list, [2] search page, [3] portal answers with its error
        // page anyway, then the whole GET-then-POST is retried: [4] option list
        // is cached now so only a search page and a result are needed.
        Http::fake([
            'efacility.jamb.gov.ng/CheckMatriculationList' => Http::sequence()
                ->push($this->goodPage(), 200)
                ->push($this->goodPage(), 200)
                ->push($this->portalErrorPage(), 200)
                ->push($this->goodPage(), 200)
                ->push($this->successPage(), 200),
        ]);

        $result = app(JambMatriculationVerificationService::class)
            ->verify(2025, '202551494080CF', 'UTME');

        $this->assertTrue($result['verified'], 'A portal error page must be retried, not returned to the student.');
        $this->assertSame('Test Candidate', $result['name']);
    }

    public function test_a_genuinely_unregistered_candidate_is_reported_as_not_found(): void
    {
        $negative = <<<HTML
        <!DOCTYPE html><html><body>
        <p>You Did not Register for this Examination</p>
        </body></html>
        HTML;

        Http::fake([
            'efacility.jamb.gov.ng/CheckMatriculationList' => Http::sequence()
                ->push($this->goodPage(), 200)
                ->push($this->goodPage(), 200)
                ->push($negative, 200),
        ]);

        $result = app(JambMatriculationVerificationService::class)
            ->verify(2025, '202551494080CF', 'UTME');

        $this->assertFalse($result['verified']);
        $this->assertSame('not_found', $result['status']);
    }
}
