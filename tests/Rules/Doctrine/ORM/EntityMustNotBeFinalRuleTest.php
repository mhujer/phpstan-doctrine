<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use Iterator;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPStan\Type\Doctrine\ObjectMetadataResolver;

class EntityMustNotBeFinalRuleTest extends RuleTestCase
{

	protected function getRule(): Rule
	{
		return new EntityMustNotBeFinalRule(
			new ObjectMetadataResolver(__DIR__ . '/entity-manager.php', null)
		);
	}

	/**
	 * @dataProvider ruleProvider
	 */
	public function testRule(string $file, array $expectedErrors): void
	{
		$this->analyse([$file], $expectedErrors);
	}

	public function ruleProvider(): Iterator
	{
		yield 'regular class' => [__DIR__ . '/data/FinalClassNotAnEntity.php', []];

		yield 'nice entity' => [__DIR__ . '/data/EntityWithRelations.php', []];

		yield 'final entity class' => [
			__DIR__ . '/data/FinalEntity.php',
			[
				[
					'Class PHPStan\Rules\Doctrine\ORM\FinalEntity is a Doctrine ORM entity, so it should not be marked as final',
					10,
				],
			],
		];
	}

}
