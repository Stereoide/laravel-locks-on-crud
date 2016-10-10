<?php

namespace Stereoide\LocksOnCrud;

trait LocksOnCrud
{
    /**
     * The internal lock id this trait method use.
     *
     * @var int
     */
    protected $automaticLockIds = null;

    public static function bootLocksOnCrud()
    {
        /* Set event listeners so we can jump in every time a model is being created, updated or deleted */

        static::creating(function ($model) {
            $locking = app('locking');
            
            /* Since this model does not yet exist we need to apply a read lock on its parent if necessary */
            
            $attributes = $model->getAttributes();
            if (isset($attributes['parent_id'])) {
                $locking->acquireReadLock($model->parent_id);
            }
            
            
        });

        static::created(function ($model) {
            $locking = app('locking');
            
            /* Release the read lock we created on the parent */

            $attributes = $model->getAttributes();
            if (isset($attributes['parent_id'])) {
                $locking->releaseLocksByEntityId($model->parent_id, $locking->READ_LOCK);
            }
        });

        static::updating(function ($model) {
            $locking = app('locking');
            
            /* Acquire write lock */

            $model->automaticLockIds = $locking->acquireWriteLock($model->id);
        });

        static::updated(function ($model) {
            $locking = app('locking');
            
            /* Release the automatically acquired locks */

            if (!empty($model->automaticLockIds)) {
                $locking->releaseLocks($model->automaticLockIds);
            }
        });

        static::deleting(function ($model) {
            $locking = app('locking');
            
            /* Read lock this models parents if necessary */

            $attributes = $model->getAttributes();
            if (isset($attributes['parent_id'])) {
                $model->automaticLockIds[] = $locking->readLockParentSequence($model->id);
            }

            /* Write lock this model */

            $model->automaticWriteLockId[] = $locking->acquireWriteLock($model->id);

            /* Refresh our locks just in case acquiring the write lock took longer than expected */

            $locking->refreshLocks();
        });

        static::deleted(function ($model) {
            $locking = app('locking');
            
            /* Release the automatically acquired locks */

            if (!empty($model->automaticLockIds)) {
                $locking->releaseLocks($model->automaticLockIds);
            }
        });
    }
}
