<?php
namespace App\Interfaces;

interface RepositoryInterface {
    public function create(array $data);
    public function update(string $id, array $data);
    public function delete(string $id): bool;
    public function findById(string $id);
    public function findAll(array $criteria = []): array;
    public function count(array $criteria = []): int;
}