<?php

namespace App\Models;

use App\Casts\EncryptedCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;

if (defined('PHPUNIT_COMPOSER_INSTALL') || defined('__PHPUNIT_PHAR__')) {
    abstract class BaseDocument extends \Illuminate\Database\Eloquent\Model {}
} else {
    abstract class BaseDocument extends \MongoDB\Laravel\Eloquent\Model {
        use \MongoDB\Laravel\Eloquent\HybridRelations;
    }
}

class Document extends BaseDocument
{
    use HasFactory;

    protected $connection = 'mongodb';
    protected $collection = 'documents';
    protected $primaryKey = '_id';
    
    public $timestamps = false;

    public function __construct(array $attributes = [])
    {
        if (defined('PHPUNIT_COMPOSER_INSTALL') || defined('__PHPUNIT_PHAR__')) {
            $this->connection = config('database.default');
            $this->table = 'documents';
        }
        parent::__construct($attributes);
    }

    protected $fillable = [
        'contract_id',
        'uuid',
        'file_name',
        'file_path',
        'document_url',
        'file_type',
        'file_size',
        'uploaded_by',
        'uploaded_at',
        'scan_status',
        'scan_result',
        'doc_category',
        'signature_method',
        'replaces_document_id',
        'signature_evidence',
    ];

    protected $casts = [
        'file_path' => EncryptedCast::class,
        'uploaded_at' => 'datetime',
        'signature_evidence' => 'array',
    ];

    public function contract()
    {
        return $this->belongsTo(Contract::class, 'contract_id', 'contract_id');
    }
}
