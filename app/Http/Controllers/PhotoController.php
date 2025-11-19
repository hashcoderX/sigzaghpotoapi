<?php

namespace App\Http\Controllers;

use App\Models\Photo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PhotoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) min(48, max(1, (int) $request->query('per_page', 12)));
        $query = Photo::with('user')->latest('id');
        
        // Search functionality
        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($userQuery) use ($search) {
                      $userQuery->where('name', 'like', "%{$search}%");
                  });
            });
        }
        
        if ($request->filled('category')) {
            $query->where('category', $request->query('category'));
        }
        if ($request->filled('is_free')) {
            $query->where('is_free', $request->boolean('is_free'));
        }
        if ($request->filled('file_type')) {
            $query->where('file_type', $request->query('file_type'));
        }
        $photos = $query->paginate($perPage);

        $photos->getCollection()->transform(function (Photo $p) {
            return [
                'id' => $p->id,
                'title' => $p->title,
                'description' => $p->description,
                'category' => $p->category,
                'file_type' => $p->file_type,
                'photographer' => $p->user?->name ?? 'Photographer',
                'is_free' => $p->is_free,
                'price' => $p->price,
                'downloads' => $p->downloads,
                'likes' => $p->likes,
                'url' => asset('storage/' . ($p->is_free ? $p->path : ($p->watermark_path ?: $p->path))),
                'created_at' => $p->created_at,
            ];
        });

        return response()->json($photos);
    }

    public function show(Photo $photo): JsonResponse
    {
        $p = $photo->load('user');
        return response()->json([
            'id' => $p->id,
            'title' => $p->title,
            'description' => $p->description,
            'category' => $p->category,
            'file_type' => $p->file_type,
            'photographer' => $p->user?->name ?? 'Photographer',
            'is_free' => $p->is_free,
            'price' => $p->is_free ? null : ($p->price ?? 0),
            'downloads' => $p->downloads,
            'likes' => $p->likes,
            'url' => asset('storage/' . ($p->is_free ? $p->path : ($p->watermark_path ?: $p->path))),
            'created_at' => $p->created_at,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_free' => ['required', 'boolean'],
            'price' => ['nullable', 'numeric', 'min:0', 'required_if:is_free,false'],
            'image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);

        $path = $request->file('image')->store('photos/original', 'public');
        $fileType = $request->file('image')->getClientOriginalExtension();

        $watermarkPath = null;
        if (!$validated['is_free']) {
            try {
                $abs = Storage::disk('public')->path($path);
                $image = null;
                $mime = mime_content_type($abs) ?: '';
                if (str_contains($mime, 'png')) {
                    $image = imagecreatefrompng($abs);
                } elseif (str_contains($mime, 'webp')) {
                    if (function_exists('imagecreatefromwebp')) {
                        $image = imagecreatefromwebp($abs);
                    }
                } else {
                    $image = imagecreatefromjpeg($abs);
                }
                if ($image) {
                    $width = imagesx($image);
                    $height = imagesy($image);
                    $overlay = imagecreatetruecolor($width, $height);
                    imagecopy($overlay, $image, 0, 0, 0, 0, $width, $height);

                    // Semi-transparent text watermark at bottom-right using category
                    $text = strtoupper(substr($validated['category'], 0, 24));
                    $alphaColor = imagecolorallocatealpha($overlay, 255, 255, 255, 75); // white, high transparency
                    $margin = (int) max(12, min(48, $width * 0.02));
                    // Use built-in small font to avoid TTF dependency
                    $font = 5; // built-in
                    $textWidth = imagefontwidth($font) * strlen($text);
                    $textHeight = imagefontheight($font);
                    $x = $width - $textWidth - $margin;
                    $y = $height - $textHeight - $margin;
                    imagestring($overlay, $font, $x, $y, $text, $alphaColor);

                    // Save as JPEG to ensure compatibility
                    ob_start();
                    imagejpeg($overlay, null, 85);
                    $binary = ob_get_clean();
                    imagedestroy($overlay);
                    imagedestroy($image);

                    $filename = pathinfo($path, PATHINFO_FILENAME) . '.jpg';
                    $watermarkPath = 'photos/watermarked/' . $filename;
                    Storage::disk('public')->put($watermarkPath, $binary);
                }
            } catch (\Throwable $e) {
                // If watermarking fails, proceed without blocking upload
                $watermarkPath = null;
            }
        }

        $photo = Photo::create([
            'user_id' => $request->user()->id,
            'title' => $validated['title'],
            'category' => $validated['category'],
            'description' => $validated['description'] ?? null,
            'path' => $path,
            'file_type' => $fileType,
            'watermark_path' => $watermarkPath,
            'is_free' => (bool)$validated['is_free'],
            'price' => $validated['is_free'] ? null : ($validated['price'] ?? 0),
        ]);

        return response()->json([
            'status' => 'success',
            'data' => [
                'id' => $photo->id,
                'title' => $photo->title,
                'category' => $photo->category,
                'description' => $photo->description,
                'file_type' => $photo->file_type,
                'is_free' => $photo->is_free,
                'price' => $photo->price,
                'downloads' => $photo->downloads,
                'likes' => $photo->likes,
                'url' => asset('storage/' . ($photo->is_free ? $photo->path : ($photo->watermark_path ?: $photo->path))),
            ],
        ], 201);
    }

    public function my(Request $request): JsonResponse
    {
        $photos = Photo::where('user_id', $request->user()->id)
            ->latest('id')
            ->paginate(perPage: 12);

        $photos->getCollection()->transform(function (Photo $p) {
            return [
                'id' => $p->id,
                'title' => $p->title,
                'category' => $p->category,
                'description' => $p->description,
                'file_type' => $p->file_type,
                'is_free' => $p->is_free,
                'price' => $p->price,
                'downloads' => $p->downloads,
                'likes' => $p->likes,
                'url' => asset('storage/' . ($p->is_free ? $p->path : ($p->watermark_path ?: $p->path))),
                'download_url' => route('photos.download', ['photo' => $p->id]),
                'created_at' => $p->created_at,
            ];
        });

        return response()->json($photos);
    }

    public function checkout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'photo_id' => ['required', 'integer', 'exists:photos,id'],
            'return_url' => ['sometimes', 'nullable', 'string'],
            'cancel_url' => ['sometimes', 'nullable', 'string'],
        ]);

        $photo = Photo::findOrFail($validated['photo_id']);
        $price = (float) ($photo->price ?? 0);

        if ($photo->is_free || $price <= 0) {
            $photo->downloads = (int) ($photo->downloads ?? 0) + 1;
            $photo->save();

            return response()->json([
                'status' => 'paid',
                'message' => 'Photo available for immediate download.',
                'download_url' => asset('storage/' . $photo->path),
            ]);
        }

        try {
            $intentId = 'pi_' . bin2hex(random_bytes(8));
        } catch (\Throwable $e) {
            $intentId = 'pi_' . uniqid();
        }

        $amountCents = (int) round($price * 100);

        $currency = 'USD';

        $response = [
            'status' => 'requires_payment',
            'intent_id' => $intentId,
            'amount' => $price,
            'amount_cents' => $amountCents,
            'currency' => $currency,
        ];

        if (!empty($validated['return_url'])) {
            $response['return_url'] = $validated['return_url'];
        }
        if (!empty($validated['cancel_url'])) {
            $response['cancel_url'] = $validated['cancel_url'];
        }

        $paymentQuery = array_filter([
            'intent' => $intentId,
            'photo' => $photo->id,
            'amount' => $price,
            'currency' => $currency,
            'return_url' => $response['return_url'] ?? null,
            'cancel_url' => $response['cancel_url'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');

        $response['payment_url'] = url('/mock-photo-payment') . ($paymentQuery ? ('?' . http_build_query($paymentQuery)) : '');

        return response()->json($response);
    }

    public function download(Photo $photo)
    {
        if (!$photo->is_free) {
            return response()->json([
                'status' => 'error',
                'message' => 'This photo is paid and cannot be downloaded for free.',
            ], 403);
        }

        $photo->downloads = ($photo->downloads ?? 0) + 1;
        $photo->save();
        $absolute = Storage::disk('public')->path($photo->path);
        return response()->download($absolute);
    }

    public function like(Photo $photo): JsonResponse
    {
        $photo->likes = ($photo->likes ?? 0) + 1;
        $photo->save();
        return response()->json([
            'status' => 'success',
            'data' => [
                'id' => $photo->id,
                'likes' => $photo->likes,
            ],
        ]);
    }

    /**
     * Generate an AI-enhanced preview of the photo.
     * For paid photos, enhancement uses the watermarked/preview path to avoid exposing originals.
     */
    public function enhance(Photo $photo, Request $request): JsonResponse
    {
        try {
            // Decide source path: use watermarked for paid, original for free
            $relative = $photo->is_free
                ? $photo->path
                : ($photo->watermark_path ?: $photo->path);

            if (!$relative) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Source image not found.'
                ], 404);
            }

            $absPath = Storage::disk('public')->path($relative);
            if (!is_file($absPath)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Image file missing.'
                ], 404);
            }

            // Load image via GD
            $mime = mime_content_type($absPath) ?: '';
            $img = null;
            if (str_contains($mime, 'png')) {
                $img = @imagecreatefrompng($absPath);
            } elseif (str_contains($mime, 'webp')) {
                if (function_exists('imagecreatefromwebp')) {
                    $img = @imagecreatefromwebp($absPath);
                }
            } else {
                $img = @imagecreatefromjpeg($absPath);
            }
            if (!$img) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unsupported or corrupt image.'
                ], 415);
            }

            $w = imagesx($img);
            $h = imagesy($img);

            // 1) Slight denoise/smooth
            @imagefilter($img, IMG_FILTER_SMOOTH, 5);
            // 2) Auto contrast and a touch of brightness
            @imagefilter($img, IMG_FILTER_CONTRAST, -10);
            @imagefilter($img, IMG_FILTER_BRIGHTNESS, 5);
            // 3) Vibrance (approximate using colorize + negative contrast)
            @imagefilter($img, IMG_FILTER_COLORIZE, 10, 0, 10, 0);
            // 4) Sharpen using convolution kernel
            $matrix = [
                [-1, -1, -1],
                [-1, 16, -1],
                [-1, -1, -1],
            ];
            @imageconvolution($img, $matrix, 8, 0);

            // Output path
            $filename = pathinfo($relative, PATHINFO_FILENAME);
            $enhancedRel = 'photos/enhanced/' . $filename . '-' . time() . '.jpg';
            // Ensure directory exists
            Storage::disk('public')->makeDirectory('photos/enhanced');

            // Save as JPEG for compatibility
            ob_start();
            imagejpeg($img, null, 90);
            $binary = ob_get_clean();
            imagedestroy($img);

            Storage::disk('public')->put($enhancedRel, $binary);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'url' => asset('storage/' . $enhancedRel),
                    'source' => asset('storage/' . $relative),
                    'width' => $w,
                    'height' => $h,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to enhance image.'
            ], 500);
        }
    }
}
