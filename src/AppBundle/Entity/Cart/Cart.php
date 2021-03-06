<?php

namespace AppBundle\Entity\Cart;

use AppBundle\Entity\Address;
use AppBundle\Entity\Menu\MenuItem;
use AppBundle\Entity\Restaurant;
use AppBundle\Validator\Constraints\IsValidDeliveryDate;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class AddProductException extends \Exception {}

class UnavailableProductException extends AddProductException {}

class RestaurantMismatchException extends AddProductException {}


/**
 * Class Cart
 * @package AppBundle\Utils
 *
 * @IsValidDeliveryDate(groups="cart")
 *
 * @ORM\Entity
 */
class Cart
{
    /**
     * @var int
     *
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected $id;

    /**
     *
     * Restaurant the cart is linked to
     *
     * @var Restaurant
     * @ORM\ManyToOne(targetEntity="AppBundle\Entity\Restaurant", cascade={"all"})
     *
     */
    private $restaurant;

    /**
     * Delivery address for the cart
     *
     * @var Address
     *
     * @ORM\ManyToOne(targetEntity="AppBundle\Entity\Address", cascade={"all"})
     *
     */
    private $address;

    /**
     * Distance to deliver for the cart
     *
     * @var int
     *
     * @ORM\Column(type="integer", nullable=true)
     */
    private $distance;

    /**
     * Delivery date for the cart
     *
     * @var string
     *
     * @ORM\Column(type="datetime")
     */
    private $date;

    /**
     * @var CartItem[]
     *
     * @ORM\OneToMany(targetEntity="CartItem", mappedBy="cart", cascade={"all"})
     */
    private $items;

    /**
     * Cart constructor.
     * @param Restaurant|null $restaurant
     */
    public function __construct(Restaurant $restaurant = null)
    {
        $this->restaurant = $restaurant;
        $this->items = new ArrayCollection();
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId(int $id)
    {
        $this->id = $id;
    }

    public function isForRestaurant(Restaurant $restaurant)
    {
        return $this->getRestaurantId() === $restaurant->getId();
    }

    public function getRestaurantId()
    {
        if (!is_null($this->restaurant)) {
            return $this->restaurant->getId();
        }
    }

    public function getRestaurant() {
        return $this->restaurant;
    }

    public function addItem(MenuItem $menuItem, $quantity = 1, $modifierChoices = [])
    {
        if (!$menuItem->getIsAvailable()) {
            throw new UnavailableProductException(
                sprintf('Product %s is not available', $menuItem->getId())
            );
        }

        if ($this->getRestaurantId() && $menuItem->getRestaurant()->getId() != $this->getRestaurantId()) {
            throw new RestaurantMismatchException(
                sprintf('Product %s doesn\'t belong to restaurant %s', $menuItem->getId(), $this->getRestaurantId())
            );
        }

        $cartItem = new CartItem($this, $menuItem, $quantity, $modifierChoices);
        $itemKey = $cartItem->getKey();

        $criteria = Criteria::create()->where(Criteria::expr()->eq('key', $itemKey));
        $item = $this->items->matching($criteria)->first();

        if ($item) {
            $item->update($quantity);
        }
        else {
            $this->items->add($cartItem);
        }

        return $this->items;

    }

    public function removeItem($itemKey)
    {
        $criteria = Criteria::create()->where(Criteria::expr()->eq('key', $itemKey));
        $item = $this->items->matching($criteria)->first();

        if (!is_null($item)) {
            $this->items->removeElement($item);
        }

        return $this->items;
    }

    public function getItems()
    {
        return $this->items;
    }

    public function getTotal()
    {
        if (count($this->items) === 0) {
            return 0;
        }

        $itemsTotal = 0;

        foreach ($this->items as $item) {
            $itemsTotal += $item->getTotal();
        }

        return $itemsTotal + $this->restaurant->getFlatDeliveryPrice();
    }

    public function getDate()
    {
        return $this->date;
    }

    public function setDate($date)
    {
        $this->date = $date;

        return $this;
    }

    /**
     * @return int
     */
    public function getDistance()
    {
        return $this->distance;
    }

    /**
     * @param int $distance
     */
    public function setDistance(int $distance)
    {
        $this->distance = $distance;
    }

    /**
     * @return Address
     */
    public function getAddress()
    {
        return $this->address;
    }

    /**
     * @param Address $address
     */
    public function setAddress(Address $address)
    {
        $this->address = $address;
    }


    public function getNormalizedItems()
    {
        $serialized = [];

        foreach ($this->items as $item) {
            array_push($serialized, $item->toArray());
        }

        return $serialized;
    }

    public function toArray()
    {
        return array(
            'date' => $this->date->format('Y-m-d H:i:s'),
            'items' => $this->getNormalizedItems(),
        );
    }

    /**
     * Custom order validation.
     * @Assert\Callback(groups={"cart"})
     */
    public function validate(ExecutionContextInterface $context, $payload)
    {
        // Validate distance
        if (!is_null($this->address) && !is_null($this->address->getGeo()) && !is_null($this->restaurant)) {
            $maxDistance = $this->getRestaurant()->getMaxDistance();

            $constraint = new Assert\LessThan(['value' => $maxDistance]);
            $context
                ->getValidator()
                ->inContext($context)
                ->atPath('distance')
                ->validate($this->distance, $constraint, [Constraint::DEFAULT_GROUP]);
        }
    }
}
