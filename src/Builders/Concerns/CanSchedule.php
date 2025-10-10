<?php

namespace Eventrel\Client\Builders\Concerns;

use Carbon\Carbon;

trait CanSchedule
{
    /**
     * The scheduled time for the webhook.
     * 
     * @var Carbon|null
     */
    private ?Carbon $scheduledAt = null;

    /**
     * Schedule webhook for specific time
     */
    public function scheduleAt(Carbon $scheduledAt): self
    {
        $this->scheduledAt = $scheduledAt;

        return $this;
    }

    /**
     * Schedule webhook for a number of seconds from now
     */
    public function scheduleIn(int $seconds): self
    {
        $this->scheduledAt = now()->addSeconds($seconds);

        return $this;
    }

    /**
     * Schedule webhook for a number of minutes from now
     */
    public function scheduleInMinutes(int $minutes): self
    {
        $this->scheduledAt = now()->addMinutes($minutes);

        return $this;
    }

    /**
     * Schedule webhook for a number of hours from now
     */
    public function scheduleInHours(int $hours): self
    {
        $this->scheduledAt = now()->addHours($hours);

        return $this;
    }

    /**
     * Get the scheduled time
     */
    public function getScheduledAt(): ?Carbon
    {
        return $this->scheduledAt;
    }
}
