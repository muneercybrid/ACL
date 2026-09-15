<?php

namespace Tests\Feature\Jamb;

use App\Services\Jamb\JambMatriculationVerificationService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class JambMatriculationVerificationServiceTest extends TestCase
{
    private JambMatriculationVerificationService $service;

    // Dummy registration number used only in tests — never a real candidate value.
    private const TEST_REG = '123456789AB';

    // ----- GET fixture (examination dropdown + ASP.NET state fields) -----

    private const GET_HTML = <<<'HTML'
<!DOCTYPE html>
<html>
<body>
<h1>JAMB Matriculation List</h1>
<div id="ddlExamination_wrapper">
<select id="ddlExamination">
<option value="">Select Examination</option>
<option value="40">2026 Unified Tertiary Matriculation Examination (UTME)</option>
<option value="38">2025 Unified Tertiary Matriculation Examination (UTME)</option>
<option value="36">2024 Unified Tertiary Matriculation Examination (UTME)</option>
<option value="30">2018 University Matriculation Examination (UME)</option>
</select>
</div>
<input type="text" id="txtRegNumber" name="txtRegNumber" />
<input type="hidden" name="__VIEWSTATE" id="__VIEWSTATE" value="dDwtNTE5MDIwNTA0Ozs+d03FKn" />
<input type="hidden" name="__VIEWSTATEGENERATOR" id="__VIEWSTATEGENERATOR" value="92CA17B5" />
<input type="hidden" name="__EVENTVALIDATION" id="__EVENTVALIDATION" value="wEWBQKC1rKzBwLS" />
<a id="lnkSearch" href="javascript:__doPostBack('lnkSearch','')">Fetch My Details</a>
</body>
</html>
HTML;

    // ----- POST fixture: successful matriculation result -----

    private const SUCCESS_HTML = <<<'HTML'
<!DOCTYPE html>
<html>
<body>
<div id="dvdisplay" class="row">
<h1>JAMB Matriculation List</h1>
<div class="profile-info">
<h1>Hussaini Salma</h1>
</div>
<div class="col-md-8">
<a>Institution:
<span>Bayero University, Kano, Kano State</span></a>
<a>Programme:
<span>Mass Communication</span></a>
</div>
<div class="col-md-4">
<strong>Status: </strong>
<b style="color: green">Congratulations, you are on the Matriculation List</b>
</div>
</div>
<p>It is important that you confirm your name from the matriculation list.
Only candidates whose names are on this list are recognised.</p>
<p>You Did not Register for this Examination...</p>
</body>
</html>
HTML;

    // ----- POST fixture: candidate not registered -----

    private const NOT_FOUND_HTML = <<<'HTML'
<!DOCTYPE html>
<html>
<body>
<div id="dvdisplay" class="row">
<h1>JAMB Matriculation List</h1>
<div class="alert alert-warning">
<p>You Did not Register for this Examination...</p>
</div>
<p>It is important that you confirm your name from the matriculation list.
Only candidates whose names on this list are recognised as bonafide students.</p>
</div>
</body>
</html>
HTML;

    // ----- POST fixture: ambiguous / unrecognisable response -----

    private const AMBIGUOUS_HTML = <<<'HTML'
<!DOCTYPE html>
<html>
<body>
<h1>JAMB Matriculation List</h1>
<select id="ddlExamination">
<option value="38">2025 Unified Tertiary Matriculation Examination (UTME)</option>
</select>
<p>Select Examination and enter your Registration Number.</p>
</body>
</html>
HTML;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new JambMatriculationVerificationService();
        Cache::forget('jamb_exam_options');
    }

    // ------------------------------------------------------------------
    // Test 1 — Successful verification
    // ------------------------------------------------------------------

    public function test_successful_verification_extracts_candidate_data(): void
    {
        Http::fake(function ($request) {
            if ($request->method() === 'GET') {
                return Http::response(self::GET_HTML, 200);
            }

            return Http::response(self::SUCCESS_HTML, 200);
        });

        $result = $this->service->verify(2025, self::TEST_REG, 'UTME');

        $this->assertTrue($result['verified']);
        $this->assertEquals('Hussaini Salma', $result['name']);
        $this->assertEquals('Bayero University, Kano, Kano State', $result['institution']);
        $this->assertEquals('Mass Communication', $result['programme']);
        $this->assertNotNull($result['status']);
        $this->assertStringContainsString('Congratulations', $result['status']);
        $this->assertEquals(200, $result['provider_status_code']);
        $this->assertEquals('38', $result['jamb_exam_value']);
    }

    // ------------------------------------------------------------------
    // Test 2 — Candidate not registered
    // ------------------------------------------------------------------

    public function test_candidate_not_registered_returns_not_found(): void
    {
        Http::fake(function ($request) {
            if ($request->method() === 'GET') {
                return Http::response(self::GET_HTML, 200);
            }

            return Http::response(self::NOT_FOUND_HTML, 200);
        });

        $result = $this->service->verify(2025, self::TEST_REG, 'UTME');

        $this->assertFalse($result['verified']);
        $this->assertEquals('not_found', $result['status']);
        $this->assertArrayHasKey('message', $result);
        $this->assertStringContainsString(
            'registration number',
            strtolower($result['message'])
        );
        $this->assertNull($result['name']);
        $this->assertNull($result['institution']);
        $this->assertNull($result['programme']);
    }

    // ------------------------------------------------------------------
    // Test 3 — Timeout returns provider_timeout (NOT not_found)
    // ------------------------------------------------------------------

    public function test_timeout_returns_provider_timeout(): void
    {
        Http::fake(function ($request) {
            if ($request->method() === 'GET') {
                return Http::response(self::GET_HTML, 200);
            }

            throw new ConnectionException('cURL error 28: Operation timed out after 30000 milliseconds');
        });

        $result = $this->service->verify(2025, self::TEST_REG, 'UTME');

        $this->assertFalse($result['verified']);
        $this->assertEquals('provider_timeout', $result['status']);
        $this->assertNotEquals('not_found', $result['status']);
        $this->assertArrayHasKey('message', $result);
    }

    // ------------------------------------------------------------------
    // Test 4 — Provider unavailable
    // ------------------------------------------------------------------

    public function test_provider_unavailable_returns_provider_unavailable(): void
    {
        Http::fake(function ($request) {
            if ($request->method() === 'GET') {
                return Http::response(self::GET_HTML, 200);
            }

            throw new ConnectionException('cURL error 7: Failed to connect to efacility2.jamb.gov.ng port 443');
        });

        $result = $this->service->verify(2025, self::TEST_REG, 'UTME');

        $this->assertFalse($result['verified']);
        $this->assertEquals('provider_unavailable', $result['status']);
        $this->assertNotEquals('not_found', $result['status']);
    }

    // ------------------------------------------------------------------
    // Test 5 — Unexpected HTML (neither success nor deterministic negative)
    // ------------------------------------------------------------------

    public function test_unexpected_html_returns_ambiguous(): void
    {
        Http::fake(function ($request) {
            if ($request->method() === 'GET') {
                return Http::response(self::GET_HTML, 200);
            }

            return Http::response(self::AMBIGUOUS_HTML, 200);
        });

        $result = $this->service->verify(2025, self::TEST_REG, 'UTME');

        $this->assertFalse($result['verified']);
        $this->assertEquals('ambiguous', $result['status']);
        $this->assertNotTrue($result['verified'], 'Ambiguous response must never be treated as verified');
    }

    // ------------------------------------------------------------------
    // Test 6 — Examination option parsing
    // ------------------------------------------------------------------

    public function test_examination_option_resolved_from_dropdown(): void
    {
        $capturedPayload = null;

        Http::fake(function ($request) use (&$capturedPayload) {
            if ($request->method() === 'GET') {
                return Http::response(self::GET_HTML, 200);
            }

            $capturedPayload = $request->data();

            return Http::response(self::SUCCESS_HTML, 200);
        });

        $result = $this->service->verify(2025, self::TEST_REG, 'UTME');

        // 2025 UTME should resolve to value "38".
        $this->assertEquals('38', $capturedPayload['ddlExamination']);
        $this->assertEquals('38', $result['jamb_exam_value']);
        $this->assertStringContainsString('2025', $result['jamb_exam_text']);
    }

    // ------------------------------------------------------------------
    // Test 7 — Web Forms state fields used in POST
    // ------------------------------------------------------------------

    public function test_web_forms_state_carried_from_get_to_post(): void
    {
        $capturedPayload = null;

        Http::fake(function ($request) use (&$capturedPayload) {
            if ($request->method() === 'GET') {
                return Http::response(self::GET_HTML, 200);
            }

            $capturedPayload = $request->data();

            return Http::response(self::SUCCESS_HTML, 200);
        });

        $this->service->verify(2025, self::TEST_REG, 'UTME');

        $this->assertNotNull($capturedPayload, 'POST request must have been made');
        $this->assertArrayHasKey('__VIEWSTATE', $capturedPayload);
        $this->assertEquals('dDwtNTE5MDIwNTA0Ozs+d03FKn', $capturedPayload['__VIEWSTATE']);
        $this->assertArrayHasKey('__VIEWSTATEGENERATOR', $capturedPayload);
        $this->assertEquals('92CA17B5', $capturedPayload['__VIEWSTATEGENERATOR']);
        $this->assertArrayHasKey('__EVENTVALIDATION', $capturedPayload);
        $this->assertEquals('wEWBQKC1rKzBwLS', $capturedPayload['__EVENTVALIDATION']);
        $this->assertEquals('lnkSearch', $capturedPayload['__EVENTTARGET']);
        $this->assertArrayHasKey('txtRegNumber', $capturedPayload);
    }

    // ------------------------------------------------------------------
    // Test 8 — Registration number privacy (never in raw_text or errors)
    // ------------------------------------------------------------------

    public function test_registration_number_not_exposed_in_results(): void
    {
        Http::fake(function ($request) {
            if ($request->method() === 'GET') {
                return Http::response(self::GET_HTML, 200);
            }

            return Http::response(self::SUCCESS_HTML, 200);
        });

        $result = $this->service->verify(2025, self::TEST_REG, 'UTME');

        // raw_text must be a safe classification label, not candidate data.
        $this->assertIsString($result['raw_text']);
        $this->assertStringNotContainsString(
            self::TEST_REG,
            $result['raw_text'],
            'raw_text must never contain the registration number'
        );
        $this->assertStringNotContainsString(
            'Hussaini',
            $result['raw_text'],
            'raw_text must not contain candidate name'
        );
    }

    // ------------------------------------------------------------------
    // Test 9 — Missing examination option returns invalid_input
    // ------------------------------------------------------------------

    public function test_missing_examination_option_returns_invalid_input(): void
    {
        // GET fixture with only 2026 — does NOT include 2025 UTME.
        $limitedGetHtml = str_replace(
            '<option value="38">2025 Unified Tertiary Matriculation Examination (UTME)</option>',
            '',
            self::GET_HTML
        );

        Http::fake(fn () => Http::response($limitedGetHtml, 200));

        $result = $this->service->verify(2025, self::TEST_REG, 'UTME');

        $this->assertFalse($result['verified']);
        $this->assertEquals('invalid_input', $result['status']);
        $this->assertNull($result['jamb_exam_value']);
    }

    // ------------------------------------------------------------------
    // Test 10 — Fresh session isolation (no cross-request state leak)
    // ------------------------------------------------------------------

    public function test_each_verification_uses_fresh_session(): void
    {
        $requestCount = 0;

        Http::fake(function ($request) use (&$requestCount) {
            $requestCount++;

            if ($request->method() === 'GET') {
                return Http::response(self::GET_HTML, 200);
            }

            return Http::response(self::SUCCESS_HTML, 200);
        });

        // First verification — options + state + search = 3 requests.
        $result1 = $this->service->verify(2025, self::TEST_REG, 'UTME');
        $firstCount = $requestCount;

        // Clear options cache to force a fresh options fetch.
        Cache::forget('jamb_exam_options');

        // Second verification — should also work independently.
        $result2 = $this->service->verify(2025, self::TEST_REG, 'UTME');

        $this->assertTrue($result1['verified']);
        $this->assertTrue($result2['verified']);
        // Each verify makes 3 requests (GET options + GET state + POST).
        // Total: 6 across both calls.
        $this->assertEquals(6, $requestCount);
    }
}