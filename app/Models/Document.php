<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'file_path', 'file_hash', 'signature'];

    // to encrypt the stored document hash
    // protected $casts = [
    //     'file_hash' => 'encrypted',
    // ];

    // Relationship with User model
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
