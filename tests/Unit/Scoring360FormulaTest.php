<?php

namespace Tests\Unit;

use App\Services\Scoring360Service;
use Tests\TestCase;

class Scoring360FormulaTest extends TestCase
{
    public function test_excel_like_scoring_gte_thresholds(): void
    {
        $service = new Scoring360Service;
        $coeffs = ['strong' => 1.0, 'medium' => 0.6, 'weak' => 0.2];

        $eval = $this->callPrivate($service, 'evaluateCriterion', [1.5, 30.0, 1.5, 1.1, 'gte', $coeffs]);
        $this->assertSame('strong', $eval['level']);
        $this->assertSame(30.0, $eval['score']);

        $eval = $this->callPrivate($service, 'evaluateCriterion', [1.2, 30.0, 1.5, 1.1, 'gte', $coeffs]);
        $this->assertSame('medium', $eval['level']);
        $this->assertSame(18.0, $eval['score']);

        $eval = $this->callPrivate($service, 'evaluateCriterion', [0.9, 30.0, 1.5, 1.1, 'gte', $coeffs]);
        $this->assertSame('weak', $eval['level']);
        $this->assertSame(6.0, $eval['score']);
    }

    public function test_excel_like_scoring_lte_thresholds(): void
    {
        $service = new Scoring360Service;
        $coeffs = ['strong' => 1.0, 'medium' => 0.6, 'weak' => 0.2];

        $eval = $this->callPrivate($service, 'evaluateCriterion', [0.5, 20.0, 0.5, 0.7, 'lte', $coeffs]);
        $this->assertSame('strong', $eval['level']);
        $this->assertSame(20.0, $eval['score']);

        $eval = $this->callPrivate($service, 'evaluateCriterion', [0.65, 20.0, 0.5, 0.7, 'lte', $coeffs]);
        $this->assertSame('medium', $eval['level']);
        $this->assertSame(12.0, $eval['score']);

        $eval = $this->callPrivate($service, 'evaluateCriterion', [0.9, 20.0, 0.5, 0.7, 'lte', $coeffs]);
        $this->assertSame('weak', $eval['level']);
        $this->assertSame(4.0, $eval['score']);
    }

    public function test_missing_value_scores_zero(): void
    {
        $service = new Scoring360Service;
        $coeffs = ['strong' => 1.0, 'medium' => 0.6, 'weak' => 0.2];

        $eval = $this->callPrivate($service, 'evaluateCriterion', [null, 30.0, 1.5, 1.1, 'gte', $coeffs]);
        $this->assertSame('missing', $eval['level']);
        $this->assertSame(0.0, $eval['score']);
    }

    public function test_decision_thresholds_match_excel_style(): void
    {
        $service = new Scoring360Service;
        $decision = [
            'strong_min' => 80.0,
            'medium_min' => 60.0,
            'labels' => ['strong' => 'OK', 'medium' => 'WATCH', 'weak' => 'RISK'],
            'lectures' => ['strong' => 'A', 'medium' => 'B', 'weak' => 'C'],
        ];

        $d = $this->callPrivate($service, 'decisionFromScore', [80.0, $decision]);
        $this->assertSame('strong', $d['level']);
        $this->assertSame('OK', $d['label']);

        $d = $this->callPrivate($service, 'decisionFromScore', [60.0, $decision]);
        $this->assertSame('medium', $d['level']);
        $this->assertSame('WATCH', $d['label']);

        $d = $this->callPrivate($service, 'decisionFromScore', [59.9, $decision]);
        $this->assertSame('weak', $d['level']);
        $this->assertSame('RISK', $d['label']);
    }

    /**
     * @return array<string, mixed>
     */
    private function callPrivate(object $obj, string $method, array $args): array
    {
        $ref = new \ReflectionClass($obj);
        $m = $ref->getMethod($method);
        $m->setAccessible(true);

        /** @var array<string, mixed> $out */
        $out = $m->invokeArgs($obj, $args);

        return $out;
    }
}
