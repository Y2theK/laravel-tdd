<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProductsTest extends TestCase
{
    use RefreshDatabase;
    private $user;
    private $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->createUser();
        $this->admin = $this->createUser(true);
    }

    public function test_unauthenticated_user_cannot_access_products_page()
    {
        $response = $this->get('/products');

        $response->assertRedirect('login');
        $response->assertStatus(302);
    }

    public function test_homepage_contains_empty_products_table()
    {
        $response = $this->actingAs($this->user)->get('/products');

        $response->assertSee('No products found');
        $response->assertStatus(200);
    }

    public function test_homepage_contains_non_empty_products_table()
    {
        $product = Product::create([
            'name' => 'product 1',
            'price' => 100
        ]);

        $response = $this->actingAs($this->user)->get('/products');

        $response->assertStatus(200);
        $response->assertDontSee('No products found');
        $response->assertSee($product->name);
        $response->assertViewHas('products', function ($collection) use ($product) {
            return $collection->contains($product);
        });
    }

    public function test_non_admin_dont_see_create_edit_delete_button()
    {
        $response = $this->actingAs($this->user)->get('/products');

        $response->assertDontSee('Add new product');
        $response->assertDontSee('Edit');
        $response->assertDontSee('Delete');
        $response->assertStatus(200);
    }

    public function test_admin_see_create_edit_delete_button()
    {
        $products = Product::factory(10)->create();
        $response = $this->actingAs($this->admin)->get('/products');

        $response->assertSee('Add new product');
        $response->assertSee('Edit');
        $response->assertSee('Delete');
        $response->assertStatus(200);
    }

    public function test_pagination_for_products_table()
    {
        $product = Product::factory(11)->create();
        $lastProduct = $product->last();

        $response = $this->actingAs($this->user)->get('/products');

        $response->assertOk();
        $response->assertViewHas('products', function ($collection) use ($lastProduct) {
            return !$collection->contains($lastProduct);
        });
    }

    public function test_non_admin_cant_access_create_edit_delete_route()
    {
        $response = $this->actingAs($this->user)->get('/products/create');

        $response->assertStatus(403);
    }

    public function test_admin_can_access_create_edit_delete_route()
    {
        $response = $this->actingAs($this->admin)->get('/products/create');

        $response->assertStatus(200);
    }

    public function test_product_image_upload_successfully()
    {
        Storage::fake();

        $filename = 'sample.jpg';
        $product = [
            'name' => 'Product Sample',
            'price' => 1234,
            'photo' => UploadedFile::fake()->image($filename), 
        ];

        $response = $this->actingAs($this->admin)->post('/products', $product);

        $response->assertStatus(302);
        
        $lastProduct = Product::latest()->first();

        $this->assertEquals($filename, $lastProduct->photo);

        Storage::assertExists('products/' . $filename);

    }
    
    public function test_admin_can_create_product()
    {
        $product = [
            'name' => 'admin product',
            'price' => 101
        ];

        $response = $this->followingRedirects()->actingAs($this->admin)->post('/products', $product);

        $response->assertStatus(200);
        $response->assertSeeText($product['name']);

        $this->assertDatabaseHas('products', $product);

        $lastProduct = Product::latest()->first();

        $this->assertEquals($product['name'], $lastProduct->name);
        $this->assertEquals($product['price'], $lastProduct->price);
    }

    public function test_edit_form_has_correct_data()
    {
        $product = Product::factory()->create();

        $this->assertDatabaseHas('products', [
            'name' => $product->name,
            'price' => $product->price,
        ]);

        $this->assertModelExists($product);

        $response = $this->actingAs($this->admin)->get('/products/' . $product->id . '/edit');

        $response->assertOk();
        $response->assertSee($product->name);
        $response->assertSee($product->price);
        $response->assertViewHas('product', $product);
    }

    public function test_update_product_validation_fails_redirect_to_form()
    {
        $product = Product::factory()->create();

        $response = $this->actingAs($this->admin)->put('/products/' . $product->id, [
            'name' => '',
            'price' => ''
        ]);

        $response->assertInvalid(['name', 'price']);
        $response->assertStatus(302);
    }

    public function test_product_delete_successful()
    {
        $product = Product::factory()->create();

        $response = $this->actingAs($this->admin)->delete('/products/' . $product->id);

        $response->assertStatus(302);
        $response->assertRedirect('products');

        $this->assertDatabaseMissing('products', $product->toArray());
        $this->assertDatabaseCount('products', 0);
        $this->assertModelMissing($product);
    }

    private function createUser($is_admin = false)
    {
        return User::factory()->create([
            'is_admin' => $is_admin
        ]);
    }
}
