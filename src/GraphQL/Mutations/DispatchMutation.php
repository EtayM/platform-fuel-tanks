<?php

namespace Enjin\Platform\FuelTanks\GraphQL\Mutations;

use Closure;
use Enjin\Platform\FuelTanks\Rules\IsFuelTankOwner;
use Enjin\Platform\FuelTanks\Rules\RuleSetExists;
use Enjin\Platform\FuelTanks\Rules\ValidMutation;
use Enjin\Platform\FuelTanks\Services\TransactionService;
use Enjin\Platform\GraphQL\Types\Input\Substrate\Traits\HasIdempotencyField;
use Enjin\Platform\Interfaces\PlatformBlockchainTransaction;
use Enjin\Platform\Models\Transaction;
use Enjin\Platform\Rules\MaxBigInt;
use Enjin\Platform\Rules\MinBigInt;
use Enjin\Platform\Rules\ValidSubstrateAddress;
use Enjin\Platform\Support\Hex;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Illuminate\Support\Facades\DB;
use Rebing\GraphQL\Support\Facades\GraphQL;

class DispatchMutation extends Mutation implements PlatformBlockchainTransaction
{
    use HasIdempotencyField;

    /**
     * Get the mutation's attributes.
     */
    public function attributes(): array
    {
        return [
            'name' => 'Dispatch',
            'description' => __('enjin-platform-fuel-tanks::mutation.dispatch.description'),
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
            'tankId' => [
                'type' => GraphQL::type('String!'),
                'description' => __('enjin-platform-fuel-tanks::mutation.destroy_fuel_tank.args.tankId'),
            ],
            'ruleSetId' => [
                'type' => GraphQL::type('BigInt!'),
                'description' => __('enjin-platform-fuel-tanks::mutation.schedule_mutate_freeze_state.args.ruleSetId'),
            ],
            'dispatch' => [
                'type' => GraphQL::type('DispatchInputType!'),
                'description' => __('enjin-platform-fuel-tanks::input_type.dispatch.description'),
            ],
            'paysRemainingFee' => [
                'type' => GraphQL::type('Boolean'),
                'description' => __('enjin-platform-fuel-tanks::mutation.dispatch.args.paysRemainingFee'),
                'defaultValue' => false,
            ],
            ...$this->getIdempotencyField(),
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
        TransactionService $transaction
    ) {
        return Transaction::lazyLoadSelectFields(
            DB::transaction(fn () => $transaction->dispatch($args)),
            $resolveInfo
        );
    }

    /**
     * Get the mutation's request validation rules.
     */
    protected function rules(array $args = []): array
    {
        return [
            'tankId' => [
                'bail',
                'filled',
                'max:255',
                new ValidSubstrateAddress(),
                new IsFuelTankOwner(),
            ],
            'ruleSetId' => [
                'bail',
                new MinBigInt(),
                new MaxBigInt(Hex::MAX_UINT32),
                new RuleSetExists(),
            ],
            'dispatch.query' => [
                'filled',
                new ValidMutation(),
            ],
        ];
    }
}
