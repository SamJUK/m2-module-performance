<?php
declare(strict_types=1);

namespace SamJUK\Performance\Plugin;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Review\Model\RatingFactory;
use Magento\Review\Model\Rating\Option\VoteFactory;
use Magento\Review\Model\ResourceModel\Rating\Option\Vote\Collection as VoteCollection;
use Magento\Review\Model\ResourceModel\Review\Collection;
use Magento\Store\Model\StoreManagerInterface;

// Core addRateVotes() loads votes one review at a time. This loads them in one go and hands each review a
// pre-filled collection, so templates still get a collection they can count and iterate.
class BatchReviewVotes
{
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly VoteFactory $voteFactory,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function aroundAddRateVotes(Collection $subject, callable $proceed): Collection
    {
        if (!$this->scopeConfig->isSetFlag('samjuk_performance/general/queries') || !$subject->getItems()) {
            return $proceed();
        }
        $storeId = $this->storeManager->getStore()->getId();
        /** @var VoteCollection $all */
        $all = $this->voteFactory->create()->getResourceCollection();
        $all->setStoreFilter($storeId)->addRatingInfo($storeId);
        $all->getSelect()->where('main_table.review_id IN (?)', array_keys($subject->getItems()));

        $byReview = [];
        foreach ($all as $vote) {
            $byReview[$vote->getReviewId()][] = $vote;
        }
        foreach ($subject->getItems() as $id => $review) {
            $votes = $this->voteFactory->create()->getResourceCollection();
            // ponytail: bench only, marks the per-review collection loaded without a query
            (fn () => $this->_setIsLoaded(true))->call($votes);
            foreach ($byReview[$id] ?? [] as $vote) {
                $votes->addItem($vote);
            }
            $review->setRatingVotes($votes);
        }
        return $subject;
    }
}
