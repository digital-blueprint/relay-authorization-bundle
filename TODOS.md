# TODOs

* Add granted actions to group entity to enable/disable frontend controls accordingly 
* Forbid deletion of last manage grant of an authorization resource to ensure it can be deleted
* Restrict access to dynamic groups by introducing read policies for dynamic groups in configuration
* Add filter to the public resource action grant API to allow more complex resource action grant queries like:
WHERE action = 'manage'