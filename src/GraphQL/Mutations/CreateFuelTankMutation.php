<?php

namespace Enjin\Platform\FuelTanks\GraphQL\Mutations;

use Closure;
use Enjin\BlockchainTools\HexConverter;
use Enjin\Platform\FuelTanks\Enums\CoveragePolicy;
use Enjin\Platform\FuelTanks\GraphQL\Traits\HasFuelTankValidationRules;
use Enjin\Platform\FuelTanks\Models\Substrate\AccountRulesParams;
use Enjin\Platform\FuelTanks\Services\Blockchain\Implemetations\Substrate;
use Enjin\Platform\GraphQL\Schemas\Primary\Substrate\Traits\StoresTransactions;
use Enjin\Platform\GraphQL\Schemas\Primary\Traits\HasSkippableRules;
use Enjin\Platform\GraphQL\Schemas\Primary\Traits\HasTransactionDeposit;
use Enjin\Platform\GraphQL\Types\Input\Substrate\Traits\HasIdempotencyField;
use Enjin\Platform\GraphQL\Types\Input\Substrate\Traits\HasSigningAccountField;
use Enjin\Platform\GraphQL\Types\Input\Substrate\Traits\HasSimulateField;
use Enjin\Platform\Interfaces\PlatformBlockchainTransaction;
use Enjin\Platform\Models\Transaction;
use Enjin\Platform\Services\Serialization\Interfaces\SerializationServiceInterface;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Rebing\GraphQL\Support\Facades\GraphQL;

class CreateFuelTankMutation extends Mutation implements PlatformBlockchainTransaction
{
    use HasFuelTankValidationRules;
    use HasIdempotencyField;
    use HasSigningAccountField;
    use HasSimulateField;
    use HasSkippableRules;
    use HasTransactionDeposit;
    use StoresTransactions;

    /**
     * Get the mutation's attributes.
     */
    public function attributes(): array
    {
        return [
            'name' => 'CreateFuelTank',
            'description' => __('enjin-platform-fuel-tanks::mutation.create_fuel_tank.description'),
        ];
    }

    /**
     * Get the mutation's return type.
     */
    public function type(): Type
    {
        return GraphQL::type('Transaction!');
    }

    /**
     * Get the mutation's arguments definition.
     */
    public function args(): array
    {
        return [
            'name' => [
                'type' => GraphQL::type('String!'),
                'description' => __('enjin-platform-fuel-tanks::type.fuel_tank.field.name'),
            ],
            'reservesAccountCreationDeposit' => [
                'type' => GraphQL::type('Boolean'),
                'description' => __('enjin-platform-fuel-tanks::type.fuel_tank.field.reservesAccountCreationDeposit'),
            ],
            'coveragePolicy' => [
                'type' => GraphQL::type('CoveragePolicy'),
                'description' => __('enjin-platform-fuel-tanks::type.fuel_tank.field.coveragePolicy'),
                'defaultValue' => CoveragePolicy::FEES->name,
            ],
            'accountRules' => [
                'type' => GraphQL::type('AccountRuleInputType'),
                'description' => __('enjin-platform-fuel-tanks::input_type.account_rule.description'),
            ],
            'dispatchRules' => [
                'type' => GraphQL::type('[DispatchRuleInputType!]'),
                'description' => __('enjin-platform-fuel-tanks::input_type.dispatch_rule.description'),
            ],
            ...$this->getSigningAccountField(),
            ...$this->getIdempotencyField(),
            ...$this->getSimulateField(),
            ...$this->getSkipValidationField(),
            // Deprecated fields, they don't exist on-chain anymore, should be removed at 2.1.0
            'reservesExistentialDeposit' => [
                'type' => GraphQL::type('Boolean'),
                'description' => __('enjin-platform-fuel-tanks::type.fuel_tank.field.reservesExistentialDeposit'),
                'deprecationReason' => __('enjin-platform-fuel-tanks::deprecated.fuel_tank.field.reservesExistentialDeposit'),
            ],
            'providesDeposit' => [
                'type' => GraphQL::type('Boolean'),
                'description' => __('enjin-platform-fuel-tanks::type.fuel_tank.field.providesDeposit'),
                'deprecationReason' => __('enjin-platform-fuel-tanks::deprecated.fuel_tank.field.providesDeposit'),
            ],
        ];
    }

    /**
     * Resolve the mutation's request.
     */
    public function resolve(
        $root,
        array $args,
        $context,
        ResolveInfo $resolveInfo,
        Closure $getSelectFields,
        SerializationServiceInterface $serializationService,
        Substrate $blockchainService
    ) {
        $encodedData = $serializationService->encode($this->getMutationName(), static::getEncodableParams(
            name: $args['name'],
            userAccountManagement: $blockchainService->getUserAccountManagementParams($args),
            dispatchRules: $blockchainService->getDispatchRulesParamsArray($args),
            coveragePolicy: $args['coveragePolicy'] ?? CoveragePolicy::FEES,
            accountRules: $blockchainService->getAccountRulesParams($args)
        ));

        return Transaction::lazyLoadSelectFields(
            DB::transaction(fn () => $this->storeTransaction($args, $encodedData)),
            $resolveInfo
        );
    }

    public static function getEncodableParams(...$params): array
    {
        $name = Arr::get($params, 'name', '');
        $userAccountManagement = Arr::get($params, 'userAccountManagement');
        $ruleSets = collect(Arr::get($params, 'dispatchRules', []));
        $coveragePolicy = is_string($coverage = Arr::get($params, 'coveragePolicy')) ? CoveragePolicy::getEnumCase($coverage) : $coverage;
        $accountRules = Arr::get($params, 'accountRules', new AccountRulesParams());

        return [
            'descriptor' => [
                'name' => HexConverter::stringToHexPrefixed($name),
                'userAccountManagement' => $userAccountManagement?->toEncodable(),
                'coveragePolicy' => $coveragePolicy->value,
                'ruleSets' =>  [
                    [
                        'rules' => $ruleSets->flatMap(fn ($ruleSet) => $ruleSet->toEncodable())->all(),
                        'requireAccount' => false,
                    ],
                ],
                'accountRules' => $accountRules?->toEncodable() ?? [],
            ],
        ];
    }

    /**
     * Get the mutation's validation rules.
     */
    protected function rulesWithValidation(array $args): array
    {
        return $this->validationRulesExist($args);
    }

    /**
     * Get the mutation's validation rules without DB rules.
     */
    protected function rulesWithoutValidation(array $args): array
    {
        return $this->validationRules($args);
    }
}
