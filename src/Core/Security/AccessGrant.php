<?php
declare(strict_types=1);
namespace Hostider\Core\Security;

/** A trusted, operation-specific grant, built by Identity, never from request input. */
final readonly class AccessGrant
{
    public function __construct(public string $permission, public array $customers = [],
        public array $services = [], public array $departments = []) {}

    public function covers(string $permission, ?string $customer, ?string $service, ?string $department): bool
    {
        return $this->permission === $permission
            && ($customer === null || $this->includes($this->customers, $customer))
            && ($service === null ? $this->services === [] || in_array('*',$this->services,true) : $this->includes($this->services, $service))
            && ($department === null ? $this->departments === [] || in_array('*',$this->departments,true) : $this->includes($this->departments, $department));
    }

    private function includes(array $scope, string $id): bool
    {
        return in_array($id, $scope, true) || in_array('*', $scope, true);
    }
}
