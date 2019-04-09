<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\QueryBuilder;

use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Rules\Doctrine\ORM\DynamicQueryBuilderArgumentException;
use PHPStan\Type\Doctrine\ArgumentsProcessor;
use PHPStan\Type\Doctrine\ObjectMetadataResolver;
use PHPStan\Type\Doctrine\Query\QueryType;
use PHPStan\Type\Type;
use function in_array;
use function method_exists;
use function strtolower;

class QueryBuilderGetQueryDynamicReturnTypeExtension implements \PHPStan\Type\DynamicMethodReturnTypeExtension
{

	/** @var ObjectMetadataResolver */
	private $objectMetadataResolver;

	/** @var \PHPStan\Type\Doctrine\ArgumentsProcessor */
	private $argumentsProcessor;

	/** @var string|null */
	private $queryBuilderClass;

	public function __construct(
		ObjectMetadataResolver $objectMetadataResolver,
		ArgumentsProcessor $argumentsProcessor,
		?string $queryBuilderClass
	)
	{
		$this->objectMetadataResolver = $objectMetadataResolver;
		$this->argumentsProcessor = $argumentsProcessor;
		$this->queryBuilderClass = $queryBuilderClass;
	}

	public function getClass(): string
	{
		return $this->queryBuilderClass ?? 'Doctrine\ORM\QueryBuilder';
	}

	public function isMethodSupported(MethodReflection $methodReflection): bool
	{
		return $methodReflection->getName() === 'getQuery';
	}

	public function getTypeFromMethodCall(
		MethodReflection $methodReflection,
		MethodCall $methodCall,
		Scope $scope
	): Type
	{
		$calledOnType = $scope->getType($methodCall->var);
		$defaultReturnType = ParametersAcceptorSelector::selectFromArgs(
			$scope,
			$methodCall->args,
			$methodReflection->getVariants()
		)->getReturnType();
		if (!$calledOnType instanceof QueryBuilderType) {
			return $defaultReturnType;
		}

		$objectManager = $this->objectMetadataResolver->getObjectManager();
		if ($objectManager === null) {
			return $defaultReturnType;
		}
		$entityManagerInterface = 'Doctrine\ORM\EntityManagerInterface';
		if (!$objectManager instanceof $entityManagerInterface) {
			return $defaultReturnType;
		}

		/** @var \Doctrine\ORM\EntityManagerInterface $objectManager */
		$objectManager = $objectManager;

		$queryBuilder = $objectManager->createQueryBuilder();

		foreach ($calledOnType->getMethodCalls() as $calledMethodCall) {
			if (!$calledMethodCall->name instanceof Identifier) {
				continue;
			}

			$methodName = $calledMethodCall->name->toString();
			$lowerMethodName = strtolower($methodName);
			if (in_array($lowerMethodName, [
				'setparameter',
				'setparameters',
			], true)) {
				continue;
			}

			if ($lowerMethodName === 'setfirstresult') {
				$queryBuilder->setFirstResult(0);
				continue;
			}

			if ($lowerMethodName === 'setmaxresults') {
				$queryBuilder->setMaxResults(10);
				continue;
			}

			if (!method_exists($queryBuilder, $methodName)) {
				continue;
			}

			try {
				$args = $this->argumentsProcessor->processArgs($scope, $methodName, $calledMethodCall->args);
			} catch (DynamicQueryBuilderArgumentException $e) {
				// todo parameter "detectDynamicQueryBuilders" a hlasit jako error - pro oddebugovani
				return $defaultReturnType;
			}

			$queryBuilder->{$methodName}(...$args);
		}

		return new QueryType($queryBuilder->getDQL());
	}

}
